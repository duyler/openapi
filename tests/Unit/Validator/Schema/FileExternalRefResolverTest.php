<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
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
            @unlink($tempFile);
        }
    }

    #[Test]
    public function denies_http_scheme(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(ExternalRefSecurityException::class);
        $this->expectExceptionMessage('denied: builtin resolver supports only file:// scheme');

        $resolver->resolve('http://evil.example.com/spec.yaml');
    }

    #[Test]
    public function denies_https_scheme(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve('https://evil.example.com/spec.yaml');
    }

    #[Test]
    public function denies_ftp_scheme(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve('ftp://evil.example.com/spec.yaml');
    }

    #[Test]
    public function denies_ftps_scheme(): void
    {
        $resolver = new FileExternalRefResolver();

        $this->expectException(ExternalRefSecurityException::class);

        $resolver->resolve('ftps://evil.example.com/spec.yaml');
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
            @unlink($tempFile);
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
