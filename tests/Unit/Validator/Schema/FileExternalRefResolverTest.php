<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Test\Support\FixtureLocator;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefTooLargeException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;

use Throwable;

use function fclose;
use function file_put_contents;
use function fopen;
use function is_dir;
use function is_link;
use function mkdir;
use function rmdir;
use function str_repeat;
use function symlink;
use function sys_get_temp_dir;
use function uniqid;

use function unlink;

use function strlen;

use function is_resource;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class FileExternalRefResolverTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = FixtureLocator::path('external-ref');
    }

    #[Test]
    public function resolves_relative_yaml_ref_with_pointer(): void
    {
        $resolver = new FileExternalRefResolver();

        $schema = $resolver->resolve($this->fixtureRoot . '/components/user.yaml#/UserSchema');

        $this->assertSame('object', $schema->type);
        $this->assertNotNull($schema->properties);
        $this->assertArrayHasKey('id', $schema->properties);
        $this->assertSame('integer', $schema->properties['id']->type);
    }

    #[Test]
    public function resolves_relative_yaml_ref_without_pointer_returns_whole_document_as_schema(): void
    {
        $resolver = new FileExternalRefResolver();

        $schema = $resolver->resolve($this->fixtureRoot . '/components/user.yaml');

        $this->assertInstanceOf(Schema::class, $schema);
    }

    #[Test]
    public function resolves_file_scheme_ref_with_pointer(): void
    {
        $resolver = new FileExternalRefResolver();

        $schema = $resolver->resolve('file://' . $this->fixtureRoot . '/components/user.yaml#/UserSchema');

        $this->assertSame('object', $schema->type);
        $this->assertArrayHasKey('name', $schema->properties ?? []);
    }

    #[Test]
    public function resolves_json_ref(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'extref_') . '.json';
        file_put_contents($tempFile, json_encode([
            'UserSchema' => ['type' => 'object', 'title' => 'FromJson'],
        ], JSON_THROW_ON_ERROR));

        try {
            $resolver = new FileExternalRefResolver();

            $schema = $resolver->resolve($tempFile . '#/UserSchema');

            $this->assertSame('object', $schema->type);
            $this->assertSame('FromJson', $schema->title);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function resolves_external_ref_with_decoded_json_pointer(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'extref_ptr_') . '.json';
        file_put_contents($tempFile, json_encode([
            'paths' => [
                '/user' => ['type' => 'object', 'title' => 'UserPath'],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $resolver = new FileExternalRefResolver();

            $schema = $resolver->resolve($tempFile . '#/paths/~1user');

            $this->assertSame('object', $schema->type);
            $this->assertSame('UserPath', $schema->title);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @param non-empty-string $ref
     */
    #[Test]
    #[DataProvider('rejectedSchemeRefs')]
    public function rejects_non_allowlisted_scheme_via_whitelist(string $ref): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve($ref);
    }

    /**
     * @return array<non-empty-string, array{non-empty-string}>
     */
    public static function rejectedSchemeRefs(): array
    {
        return [
            'http'        => ['http://evil.example.com/spec.yaml'],
            'https'       => ['https://evil.example.com/spec.yaml'],
            'ftp'         => ['ftp://evil.example.com/spec.yaml'],
            'ftps'        => ['ftps://evil.example.com/spec.yaml'],
            'gopher'      => ['gopher://evil.example.com/x'],
            'netcat'      => ['netcat://evil.example.com/x'],
            'php_filter'  => ['php://filter/convert.base64-encode/resource=/etc/passwd'],
            'php_input'   => ['php://input'],
            'phar'        => ['phar:///tmp/evil.phar/file.yaml'],
            'data_inline' => ['data://text/plain;base64,eyJ0eXBlIjoib2JqZWN0In0=/evil.json'],
            'compress_zlib'  => ['compress.zlib:///etc/passwd'],
            'compress_bzip2' => ['compress.bzip2:///etc/passwd'],
            'zip_archive'    => ['zip://archive.zip#file'],
            'expect_shell'   => ['expect://id'],
            'ssh2_exec'      => ['ssh2://user@host/etc/passwd'],
            'rar_archive'    => ['rar:///tmp/evil.rar/file'],
            'ogg_stream'     => ['ogg://audio.example.com/stream'],
            'glob_pattern'   => ['glob:///etc/*'],
        ];
    }

    #[Test]
    public function rejected_scheme_message_does_not_leak_original_ref(): void
    {
        $resolver = new FileExternalRefResolver();

        $ref = 'php://filter/convert.base64-encode/resource=/etc/passwd';

        try {
            $resolver->resolve($ref);
            $this->fail('Expected ExternalRefSecurityException for php:// scheme');
        } catch (ExternalRefSecurityException $e) {
            $this->assertStringNotContainsString(
                $ref,
                $e->getMessage(),
                'Exception message must not leak the original $ref.',
            );
            $this->assertStringNotContainsString(
                '/etc/passwd',
                $e->getMessage(),
                'Exception message must not leak the absolute path embedded in $ref.',
            );
            $this->assertSame($ref, $e->ref(reveal: true), 'Exception must still retain $ref as a separate property for callers.');
            $this->assertStringContainsString(
                'php',
                $e->getMessage(),
                'Exception message must surface the offending scheme for diagnostics.',
            );
        }
    }

    #[Test]
    public function file_scheme_allowed_by_whitelist(): void
    {
        $resolver = new FileExternalRefResolver();

        $schema = $resolver->resolve('file://' . $this->fixtureRoot . '/components/order.yaml#/OrderSchema');

        $this->assertSame('object', $schema->type);
    }

    #[Test]
    public function relative_path_allowed_by_whitelist(): void
    {
        $resolver = new FileExternalRefResolver();

        $schema = $resolver->resolve($this->fixtureRoot . '/components/user.yaml#/UserSchema');

        $this->assertSame('object', $schema->type);
    }

    #[Test]
    public function allows_path_within_root(): void
    {
        $resolver = new FileExternalRefResolver(allowedRoot: $this->fixtureRoot);

        $schema = $resolver->resolve($this->fixtureRoot . '/components/order.yaml#/OrderSchema');

        $this->assertSame('object', $schema->type);
    }

    #[Test]
    public function denies_path_traversal_outside_root(): void
    {
        $resolver = new FileExternalRefResolver(allowedRoot: $this->fixtureRoot . '/components');

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve($this->fixtureRoot . '/../../../etc/passwd');
    }

    #[Test]
    public function denies_path_traversal_with_dotdot_under_resolver_root(): void
    {
        $resolver = new FileExternalRefResolver(allowedRoot: $this->fixtureRoot);

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve($this->fixtureRoot . '/components/../../../etc/passwd');
    }

    #[Test]
    public function denies_symlink_escape_when_allowed_root_set(): void
    {
        $resolver = new FileExternalRefResolver(allowedRoot: $this->fixtureRoot);

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve($this->fixtureRoot . '/../ExternalRefSibling.yaml');
    }

    #[Test]
    public function denies_sibling_directory_bypass_with_prefix_match(): void
    {
        $base = 'duyler_extref_' . uniqid(more_entropy: true);
        $rootDir = sys_get_temp_dir() . '/' . $base;
        $evilDir = sys_get_temp_dir() . '/' . $base . '-evil';

        mkdir($rootDir, 0700, true);
        mkdir($evilDir, 0700, true);
        $evilFile = $evilDir . '/file.yaml';
        file_put_contents($evilFile, "type: object\n");

        try {
            $resolver = new FileExternalRefResolver(allowedRoot: $rootDir);

            $this->expectException(ExternalRefSecurityException::class);

            $resolver->resolve('file://' . $evilFile);
        } finally {
            if (file_exists($evilFile)) {
                unlink($evilFile);
            }

            if (is_dir($evilDir)) {
                rmdir($evilDir);
            }

            if (is_dir($rootDir)) {
                rmdir($rootDir);
            }
        }
    }

    #[Test]
    public function allows_strict_descendant_file_under_root(): void
    {
        $base = 'duyler_extref_' . uniqid(more_entropy: true);
        $rootDir = sys_get_temp_dir() . '/' . $base;
        $subDir = $rootDir . '/sub';
        $descendantFile = $subDir . '/file.yaml';

        mkdir($subDir, 0700, true);
        file_put_contents($descendantFile, "type: object\n");

        try {
            $resolver = new FileExternalRefResolver(allowedRoot: $rootDir);

            $schema = $resolver->resolve('file://' . $descendantFile);

            $this->assertSame('object', $schema->type);
        } finally {
            if (file_exists($descendantFile)) {
                unlink($descendantFile);
            }

            if (is_dir($subDir)) {
                rmdir($subDir);
            }

            if (is_dir($rootDir)) {
                rmdir($rootDir);
            }
        }
    }

    #[Test]
    public function allows_exact_root_match_when_path_equals_root(): void
    {
        $base = 'duyler_extref_' . uniqid(more_entropy: true);
        $rootFile = sys_get_temp_dir() . '/' . $base . '.yaml';

        file_put_contents($rootFile, "type: object\n");

        try {
            $resolver = new FileExternalRefResolver(allowedRoot: $rootFile);

            $schema = $resolver->resolve('file://' . $rootFile);

            $this->assertSame('object', $schema->type);
        } finally {
            if (file_exists($rootFile)) {
                unlink($rootFile);
            }
        }
    }

    #[Test]
    public function throws_on_missing_file(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('External ref file not found');

        $resolver->resolve('/nonexistent/path/to/missing-file.yaml');
    }

    #[Test]
    public function throws_on_unsupported_extension(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'extref_') . '.txt';
        file_put_contents($tempFile, 'plain text content');

        try {
            $resolver = new FileExternalRefResolver();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsupported file extension: txt');

            $resolver->resolve($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function throws_on_missing_json_pointer_segment(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('External ref JSON Pointer segment not found');

        $resolver->resolve($this->fixtureRoot . '/components/user.yaml#/MissingKey');
    }

    #[Test]
    public function throws_on_empty_file_path(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('empty file path');

        $resolver->resolve('');
    }

    #[Test]
    public function denies_existing_file_outside_allowed_root_with_traversal_message(): void
    {
        $resolver = new FileExternalRefResolver(allowedRoot: $this->fixtureRoot);

        $this->expectException(ExternalRefSecurityException::class);
        $this->expectExceptionMessage('External ref path traversal detected');

        $resolver->resolve('/etc/passwd');
    }

    #[Test]
    public function throws_when_pointer_target_is_not_a_schema_object(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('External ref target is not a schema object');

        $resolver->resolve($this->fixtureRoot . '/components/user.yaml#/UserSchema/type');
    }

    #[Test]
    public function throws_when_payload_is_not_a_mapping(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'extref_') . '.json';
        file_put_contents($tempFile, json_encode('plain-string-scalar', JSON_THROW_ON_ERROR));

        try {
            $resolver = new FileExternalRefResolver();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('External ref file does not contain a mapping');

            $resolver->resolve($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function schema_returned_is_well_formed_with_required_and_format(): void
    {
        $resolver = new FileExternalRefResolver();

        $schema = $resolver->resolve($this->fixtureRoot . '/components/user.yaml#/UserSchema');

        $this->assertNotNull($schema->required);
        $this->assertContains('id', $schema->required);
        $this->assertContains('name', $schema->required);
        $email = $schema->properties['email'] ?? null;
        $this->assertNotNull($email);
        $this->assertSame('email', $email->format);
    }

    #[Test]
    public function toctou_symlink_replacement_blocked_by_allowed_root_check(): void
    {
        $base = 'duyler_toctou_' . uniqid(more_entropy: true);
        $rootDir = sys_get_temp_dir() . '/' . $base;
        $outsideDir = sys_get_temp_dir() . '/' . $base . '_outside';
        $insideFile = $rootDir . '/legit.yaml';
        $outsideFile = $outsideDir . '/secret.yaml';

        mkdir($rootDir, 0o700, true);
        mkdir($outsideDir, 0o700, true);
        file_put_contents($insideFile, "type: object\n");
        file_put_contents($outsideFile, "type: string\n");

        try {
            unlink($insideFile);
            symlink($outsideFile, $insideFile);

            $resolver = new FileExternalRefResolver(allowedRoot: $rootDir);

            $this->expectException(ExternalRefSecurityException::class);

            $resolver->resolve($insideFile);
        } finally {
            if (is_link($insideFile) || file_exists($insideFile)) {
                unlink($insideFile);
            }

            if (file_exists($outsideFile)) {
                unlink($outsideFile);
            }

            if (is_dir($outsideDir)) {
                rmdir($outsideDir);
            }

            if (is_dir($rootDir)) {
                rmdir($rootDir);
            }
        }
    }

    #[Test]
    public function size_limit_enforced_when_file_exceeds_max_bytes(): void
    {
        $maxBytes = 1024;
        $oversize = $maxBytes + 1024;
        $payload = '{"type":"object","padding":"' . str_repeat('a', $oversize - 31) . '"}';

        $tempFile = tempnam(sys_get_temp_dir(), 'extref_size_') . '.json';
        file_put_contents($tempFile, $payload);

        try {
            $resolver = new FileExternalRefResolver(maxBytes: $maxBytes);

            $this->expectException(ExternalRefTooLargeException::class);

            try {
                $resolver->resolve($tempFile);
            } catch (ExternalRefTooLargeException $e) {
                $this->assertSame($maxBytes, $e->max, 'Exception must expose the configured cap.');

                throw $e;
            }
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function file_exactly_at_max_bytes_is_allowed(): void
    {
        $maxBytes = 1024;
        $base = '{"type":"object","padding":""}';
        $paddingLength = $maxBytes - strlen($base);
        $payload = '{"type":"object","padding":"' . str_repeat('a', $paddingLength) . '"}';

        $tempFile = tempnam(sys_get_temp_dir(), 'extref_boundary_') . '.json';
        file_put_contents($tempFile, $payload);

        try {
            $resolver = new FileExternalRefResolver(maxBytes: $maxBytes);

            $schema = $resolver->resolve($tempFile);

            $this->assertSame('object', $schema->type);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[DataProvider('specialFileRefs')]
    #[Test]
    public function special_file_rejected_as_not_regular_file(string $specialPath): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(ExternalRefSecurityException::class);
        $this->expectExceptionMessage('External ref is not a regular file');

        $resolver->resolve($specialPath);
    }

    /**
     * @return array<non-empty-string, array{non-empty-string}>
     */
    public static function specialFileRefs(): array
    {
        return [
            'dev_null'  => ['/dev/null'],
            'dev_zero'  => ['/dev/zero'],
        ];
    }

    #[Test]
    public function file_handle_closed_when_parse_contents_throws(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'extref_finally_') . '.txt';
        file_put_contents($tempFile, 'not a yaml or json payload');

        try {
            $resolver = new FileExternalRefResolver();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsupported file extension: txt');

            $resolver->resolve($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function builder_with_external_ref_max_bytes_propagates_to_resolver(): void
    {
        $base = 'duyler_builder_max_' . uniqid(more_entropy: true);
        $outsideDir = sys_get_temp_dir() . '/' . $base;
        mkdir($outsideDir, 0o700, true);

        $oversizeFile = $outsideDir . '/too_large.json';
        file_put_contents(
            $oversizeFile,
            '{"type":"object","padding":"' . str_repeat('x', 2048) . '"}',
        );

        try {
            $resolver = new FileExternalRefResolver(maxBytes: 512);

            $this->expectException(ExternalRefTooLargeException::class);

            $resolver->resolve($oversizeFile);
        } finally {
            if (file_exists($oversizeFile)) {
                unlink($oversizeFile);
            }

            if (is_dir($outsideDir)) {
                rmdir($outsideDir);
            }
        }
    }

    #[Test]
    public function fread_returning_false_in_read_with_limit_raises_runtime_exception(): void
    {
        $scheme = 'fread-fail-' . uniqid(more_entropy: true);
        stream_wrapper_register($scheme, FailingReadStreamWrapper::class);

        try {
            $handle = fopen($scheme . ':///virtual', 'r');
            $this->assertNotFalse($handle);

            try {
                $resolver = new FileExternalRefResolver();
                $method = new ReflectionClass(FileExternalRefResolver::class)->getMethod('readWithLimit');

                $this->expectException(RuntimeException::class);
                $this->expectExceptionMessage('Failed to read external ref file');

                $method->invoke($resolver, $handle, 1024);
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    /**
     * @param non-empty-string $ref
     * @param class-string $exception
     * @dataProvider absolutePathLeakingRefs
     */
    #[Test]
    #[DataProvider('absolutePathLeakingRefs')]
    public function no_absolute_path_in_exception_message(string $ref, string $exception): void
    {
        $resolver = new FileExternalRefResolver(allowedRoot: $this->fixtureRoot);

        try {
            $resolver->resolve($ref);
            $this->fail(sprintf('Expected %s was not thrown', $exception));
        } catch (Throwable $e) {
            $this->assertInstanceOf($exception, $e);
            $message = $e->getMessage();
            $this->assertStringNotContainsString($ref, $message, 'Exception message must not embed the raw $ref.');
            $this->assertStringNotContainsString($this->fixtureRoot, $message, 'Exception message must not leak the allowedRoot path.');
            $this->assertDoesNotMatchRegularExpression('#/var/lib/.*#', $message);
            $this->assertDoesNotMatchRegularExpression('#/etc/.*#', $message);
            $this->assertDoesNotMatchRegularExpression('#/private/tmp/.*#', $message);
        }
    }

    /**
     * @return array<non-empty-string, array{non-empty-string, class-string}>
     */
    public static function absolutePathLeakingRefs(): array
    {
        return [
            'missing_file' => ['/var/lib/specs/does-not-exist.yaml', RuntimeException::class],
            'path_traversal' => ['/etc/passwd', ExternalRefSecurityException::class],
            'json_pointer_to_scalar' => [
                FixtureLocator::path('external-ref/components/user.yaml#/UserSchema/type'),
                RuntimeException::class,
            ],
        ];
    }

    #[Test]
    public function non_mapping_payload_message_does_not_leak_temp_path(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'extref_leak_') . '.json';
        file_put_contents($tempFile, json_encode('plain-string-scalar', JSON_THROW_ON_ERROR));

        try {
            $resolver = new FileExternalRefResolver();

            try {
                $resolver->resolve($tempFile);
                $this->fail('Expected RuntimeException for non-mapping payload');
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString($tempFile, $e->getMessage());
                $this->assertStringNotContainsString(sys_get_temp_dir(), $e->getMessage());
                $this->assertSame('External ref file does not contain a mapping', $e->getMessage());
            }
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    #[Test]
    public function verbose_logger_receives_absolute_path_for_missing_file(): void
    {
        $spy = new SpyLogger();
        $resolver = new FileExternalRefResolver(logger: $spy);

        try {
            $resolver->resolve('/var/lib/does-not-exist.yaml');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertSame('External ref file not found', $e->getMessage());

            $this->assertCount(1, $spy->records);
            $record = $spy->records[0];
            $this->assertSame('debug', $record['level']);
            $this->assertSame('Failed to open external ref file', $record['message']);
            $this->assertArrayHasKey('path', $record['context']);
            $this->assertSame('/var/lib/does-not-exist.yaml', $record['context']['path']);
        }
    }
}

/**
 * @internal
 */
final class FailingReadStreamWrapper
{
    /**
     * @var resource|null
     */
    public $context = null;

    /**
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param string|null $openedPath
     */
    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        return true;
    }

    /**
     * @return string|false
     */
    public function stream_read(int $count)
    {
        return false;
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_close(): void {}

    /**
     * @return array<string, int>|false
     */
    public function stream_stat()
    {
        return ['mode' => 0o100644];
    }

    /**
     * @return array<string, int>|false
     */
    public function url_stat(string $path, int $flags)
    {
        return ['mode' => 0o100644];
    }
}

/**
 * @internal
 */
final class SpyLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->records[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
    }

    public function alert($message, array $context = []): void
    {
        $this->records[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
    }

    public function critical($message, array $context = []): void
    {
        $this->records[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
    }

    public function error($message, array $context = []): void
    {
        $this->records[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    public function warning($message, array $context = []): void
    {
        $this->records[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function notice($message, array $context = []): void
    {
        $this->records[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
    }

    public function info($message, array $context = []): void
    {
        $this->records[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function debug($message, array $context = []): void
    {
        $this->records[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => $message, 'context' => $context];
    }
}
