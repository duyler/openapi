<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\ExternalRefResolverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function glob;
use function is_dir;
use function json_encode;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class ExternalRefResolverInjectionTest extends TestCase
{
    private const string MAIN_URI = 'https://main.example.com/v1/main.json';

    private const string EXTERNAL_URI = 'https://api.example.com/schema.json';

    private const string EXTERNAL_REF = 'https://api.example.com/schema.json#/components/schemas/Shared';

    private const string UNREGISTERED_REF = 'https://other.example.com/schema.json#/components/schemas/Shared';

    private const string CLIENT_REF = '#/components/schemas/Client';

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sprintf(
            '%s/external_ref_resolver_injection_%s',
            sys_get_temp_dir(),
            uniqid('', true),
        );

        mkdir($this->rootDir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    #[Test]
    public function custom_resolver_resolves_https_ref_and_validates_instance(): void
    {
        $resolver = $this->resolverFor(self::EXTERNAL_REF, new Schema(type: 'integer'));

        $validator = $this->builder()
            ->withExternalRefResolver($resolver)
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_REF]))
            ->build();

        $validator->validateSchema(42, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('forty-two', self::CLIENT_REF);
    }

    #[Test]
    public function without_custom_resolver_https_ref_is_denied_by_builtin_security(): void
    {
        $validator = $this->builder()
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_REF]))
            ->build();

        try {
            $validator->validateSchema(42, self::CLIENT_REF);
            $this->fail('Expected UnresolvableRefException to be thrown');
        } catch (UnresolvableRefException $e) {
            $this->assertInstanceOf(ExternalRefSecurityException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function last_with_external_ref_resolver_call_wins(): void
    {
        $loses = new readonly class implements ExternalRefResolverInterface {
            public function resolve(string $ref): Schema
            {
                return new Schema(type: 'string');
            }
        };
        $wins = new readonly class implements ExternalRefResolverInterface {
            public function resolve(string $ref): Schema
            {
                return new Schema(type: 'integer');
            }
        };

        $validator = $this->builder()
            ->withExternalRefResolver($loses)
            ->withExternalRefResolver($wins)
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_REF]))
            ->build();

        $validator->validateSchema(7, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('seven', self::CLIENT_REF);
    }

    #[Test]
    public function custom_resolver_exception_passes_through_unwrapped(): void
    {
        $cause = new RuntimeException('Custom resolver exploded');

        $resolver = new readonly class ($cause) implements ExternalRefResolverInterface {
            public function __construct(private RuntimeException $cause) {}

            public function resolve(string $ref): Schema
            {
                throw $this->cause;
            }
        };

        $validator = $this->builder()
            ->withExternalRefResolver($resolver)
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_REF]))
            ->build();

        try {
            $validator->validateSchema(1, self::CLIENT_REF);
            $this->fail('Expected the custom resolver exception to bubble up');
        } catch (RuntimeException $e) {
            $this->assertSame($cause, $e);
            $this->assertNull($e->getPrevious());
        }
    }

    #[Test]
    public function repository_uri_takes_priority_over_custom_resolver(): void
    {
        $loses = new readonly class implements ExternalRefResolverInterface {
            public function resolve(string $ref): Schema
            {
                return new Schema(type: 'string');
            }
        };

        $validator = $this->builder()
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson(['type' => 'integer']))
            ->withExternalRefResolver($loses)
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_REF]))
            ->build();

        $validator->validateSchema(9, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('nine', self::CLIENT_REF);
    }

    #[Test]
    public function custom_resolver_lifts_file_fallback_lock_when_repository_is_set(): void
    {
        $resolver = $this->resolverFor(self::UNREGISTERED_REF, new Schema(type: 'integer'));

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson(['type' => 'integer']))
            ->withExternalRefResolver($resolver)
            ->fromJsonString($this->inlineMainDocumentJson())
            ->build();

        $validator->validateSchema(11, self::UNREGISTERED_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('eleven', self::UNREGISTERED_REF);
    }

    private function builder(): OpenApiValidatorBuilder
    {
        return OpenApiValidatorBuilder::create()
            ->withExternalRefAllowedRoot($this->rootDir);
    }

    private function resolverFor(string $expectedRef, Schema $schema): ExternalRefResolverInterface
    {
        return new readonly class ($expectedRef, $schema) implements ExternalRefResolverInterface {
            public function __construct(
                private string $expectedRef,
                private Schema $schema,
            ) {}

            public function resolve(string $ref): Schema
            {
                if ($ref !== $this->expectedRef) {
                    throw new RuntimeException(sprintf('Unexpected ref handed to custom resolver: %s', $ref));
                }

                return $this->schema;
            }
        };
    }

    /**
     * @param array<array-key, mixed> $sharedSchema
     */
    private function sharedDocumentJson(array $sharedSchema): string
    {
        return json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'shared',
                'version' => '1.0.0',
            ],
            '$self' => self::EXTERNAL_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => $sharedSchema,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function inlineMainDocumentJson(): string
    {
        return json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'main',
                'version' => '1.0.0',
            ],
            '$self' => self::MAIN_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => ['type' => 'string'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<array-key, mixed> $clientSchema
     */
    private function mainDocumentJson(array $clientSchema): string
    {
        return json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'main',
                'version' => '1.0.0',
            ],
            '$self' => self::MAIN_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => $clientSchema,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function removeDirectory(string $directory): void
    {
        if (false === is_dir($directory)) {
            return;
        }

        /** @var list<string> $entries */
        $entries = glob($directory . '/*') ?: [];

        foreach ($entries as $item) {
            if (is_dir($item)) {
                $this->removeDirectory($item);

                continue;
            }

            unlink($item);
        }

        rmdir($directory);
    }
}
