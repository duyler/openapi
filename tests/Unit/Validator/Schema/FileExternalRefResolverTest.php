<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function dirname;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class FileExternalRefResolverTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->fixtureRoot = dirname(__DIR__, 3) . '/Fixture/ExternalRef';
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

    /**
     * SEC-01: every PHP stream wrapper outside the allowlist must be
     * rejected with ExternalRefSecurityException. The previous blacklist
     * only covered http/https/ftp/ftps/gopher/netcat and left php://,
     * phar://, data://, compress.zlib://, zip://, expect://, ssh2://,
     * rar://, ogg://, glob:// (and every future wrapper) reachable.
     *
     * Each sample is a real attack vector: php://filter reads any file via
     * base64 conversion, phar:// triggers deserialization gadgets, data://
     * injects attacker-controlled JSON (SEC-24), compress.zlib:// reads
     * files through zlib, zip:// reads entries inside archives, expect://
     * spawns interactive shells, ssh2:// reaches the network via SSH, etc.
     *
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

    /**
     * SEC-08: exception message must NOT leak the original $ref (it can
     * contain absolute paths or attacker secrets). The offending scheme
     * is fine to surface because it is a fixed, attacker-controlled token
     * from the allowlist check, not user-supplied file content.
     */
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
                'Exception message must not leak the original $ref (SEC-08).',
            );
            $this->assertStringNotContainsString(
                '/etc/passwd',
                $e->getMessage(),
                'Exception message must not leak the absolute path embedded in $ref.',
            );
            $this->assertSame($ref, $e->ref, 'Exception must still retain $ref as a separate property for callers.');
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
        $this->expectExceptionMessage('JSON Pointer segment "MissingKey" not found');

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

        // Existing fixture: user.yaml#/UserSchema/type resolves to the string
        // 'object', which is not a schema array.
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
}
