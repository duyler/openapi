<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\Exception\DocumentParseException;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefTooLargeException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function file_put_contents;
use function getcwd;
use function glob;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use function strlen;

use const JSON_THROW_ON_ERROR;

final class DocumentRepositoryTest extends TestCase
{
    private const string MAIN_URI = 'https://main.example.com/v1/main.json';

    private const string MAIN_URI_SHARED = 'https://main.example.com/v1/shared.json';

    private const string EXTERNAL_URI = 'https://api.example.com/v1/shared.json';

    private const string CLIENT_REF = '#/components/schemas/Client';

    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sprintf(
            '%s/document_repository_%s',
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
    public function cross_document_ref_resolves_through_repository(): void
    {
        $validator = $this->buildValidator(
            externalSchema: [
                'type' => 'object',
                'required' => ['name'],
                'properties' => ['name' => ['type' => 'string']],
            ],
        );

        $validator->validateSchema(['name' => 'Duyler'], self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema(['name' => 42], self::CLIENT_REF);
    }

    #[Test]
    public function relative_ref_combines_with_main_document_self_uri(): void
    {
        $main = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            '$self' => self::MAIN_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => [
                        '$ref' => 'shared.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ];

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument(
                self::MAIN_URI_SHARED,
                $this->sharedDocumentJson(['type' => 'integer'], self::MAIN_URI_SHARED),
            )
            ->fromJsonString(json_encode($main, JSON_THROW_ON_ERROR))
            ->build();

        $validator->validateSchema(7, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('not-an-integer', self::CLIENT_REF);
    }

    #[Test]
    public function ref_to_unregistered_uri_raises_unresolvable_with_uri_and_without_spec_content(): void
    {
        $main = $this->mainDocumentJson([
            '$ref' => 'https://missing.example.com/v1/nowhere.json#/components/schemas/Shared',
        ]);

        $validator = $this->builderWithSharedDocument(['type' => 'string'])
            ->withExternalRefAllowedRoot($this->rootDir)
            ->fromJsonString($main)
            ->build();

        try {
            $validator->validateSchema([], self::CLIENT_REF);
            $this->fail('Expected UnresolvableRefException to be thrown');
        } catch (UnresolvableRefException $e) {
            $this->assertStringContainsString('https://missing.example.com/v1/nowhere.json', $e->ref(reveal: true));
            $this->assertStringNotContainsString('MARKER_SPEC_CONTENT', $e->getMessage());
        }
    }

    #[Test]
    public function invalid_registered_document_fails_at_with_document_before_build(): void
    {
        $builderException = null;
        $parseException = null;

        try {
            OpenApiValidatorBuilder::create()
                ->withDocument(
                    self::EXTERNAL_URI,
                    '{"openapi": "9.9.9", "info": {}, "MARKER_SPEC_CONTENT": true}',
                );
        } catch (DocumentParseException $e) {
            $parseException = $e;
        } catch (Throwable $e) {
            $builderException = $e;
        }

        $this->assertNotNull($parseException, 'DocumentParseException must be raised at withDocument()');
        $this->assertNull($builderException);
        $this->assertStringContainsString(self::EXTERNAL_URI, $parseException->getMessage());
        $this->assertStringNotContainsString('MARKER_SPEC_CONTENT', $parseException->getMessage());
    }

    #[Test]
    public function registered_document_exceeding_external_ref_cap_is_rejected(): void
    {
        $this->expectException(ExternalRefTooLargeException::class);

        OpenApiValidatorBuilder::create()
            ->withExternalRefMaxBytes(64)
            ->withDocument(
                self::EXTERNAL_URI,
                $this->sharedDocumentJson(['type' => 'object', 'required' => [str_repeat('a', 256)]]),
            );
    }

    #[Test]
    public function repository_document_wins_over_file_resolver(): void
    {
        $this->writeFile(
            $this->rootDir . '/shared.json',
            json_encode([
                'components' => [
                    'schemas' => [
                        'Shared' => ['type' => 'string'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $main = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            '$self' => self::MAIN_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => [
                        '$ref' => 'shared.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ];

        $previousCwd = getcwd();
        chdir($this->rootDir);

        try {
            $validator = OpenApiValidatorBuilder::create()
                ->withDocument(
                    self::MAIN_URI_SHARED,
                    $this->sharedDocumentJson(['type' => 'integer'], self::MAIN_URI_SHARED),
                )
                ->withExternalRefAllowedRoot($this->rootDir)
                ->fromJsonString(json_encode($main, JSON_THROW_ON_ERROR))
                ->build();

            $validator->validateSchema(5, self::CLIENT_REF);

            $this->expectException(ValidationException::class);
            $validator->validateSchema('file-resolver-must-lose', self::CLIENT_REF);
        } finally {
            if (is_string($previousCwd)) {
                chdir($previousCwd);
            }
        }
    }

    #[Test]
    public function file_ref_without_repository_registration_still_resolves_via_file(): void
    {
        $this->writeFile(
            $this->rootDir . '/file-only.json',
            json_encode([
                'components' => [
                    'schemas' => [
                        'Shared' => ['type' => 'boolean'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $validator = OpenApiValidatorBuilder::create()
            ->withExternalRefAllowedRoot($this->rootDir)
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => $this->rootDir . '/file-only.json#/components/schemas/Shared',
            ]))
            ->build();

        $validator->validateSchema(true, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('not-a-boolean', self::CLIENT_REF);
    }

    #[Test]
    public function yaml_document_registered_by_uri_extension(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: shared
  version: 1.0.0
paths: {}
components:
  schemas:
    Shared:
      type: integer
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument('https://api.example.com/v1/shared.yaml', $yaml)
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => 'https://api.example.com/v1/shared.yaml#/components/schemas/Shared',
            ]))
            ->build();

        $validator->validateSchema(3, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema(3.5, self::CLIENT_REF);
    }

    #[Test]
    public function bare_uri_ref_resolves_to_identified_root_of_registered_document(): void
    {
        $external = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'shared', 'version' => '1.0.0'],
            '$self' => self::EXTERNAL_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        '$id' => self::EXTERNAL_URI,
                        'type' => 'integer',
                    ],
                ],
            ],
        ];

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, json_encode($external, JSON_THROW_ON_ERROR))
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_URI]))
            ->build();

        $validator->validateSchema(9, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('nine', self::CLIENT_REF);
    }

    #[Test]
    public function bare_uri_ref_to_document_without_identified_root_is_unresolvable(): void
    {
        $validator = $this->builderWithSharedDocument(['type' => 'integer'])
            ->withExternalRefAllowedRoot($this->rootDir)
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_URI]))
            ->build();

        $this->expectException(UnresolvableRefException::class);
        $validator->validateSchema(9, self::CLIENT_REF);
    }

    #[Test]
    public function plain_name_anchor_fragment_of_registered_document_resolves(): void
    {
        $external = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'shared', 'version' => '1.0.0'],
            '$self' => self::EXTERNAL_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        '$anchor' => 'shared-anchor',
                        'type' => 'integer',
                    ],
                ],
            ],
        ];

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, json_encode($external, JSON_THROW_ON_ERROR))
            ->fromJsonString($this->mainDocumentJson(['$ref' => self::EXTERNAL_URI . '#shared-anchor']))
            ->build();

        $validator->validateSchema(11, self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema('eleven', self::CLIENT_REF);
    }

    #[Test]
    public function registered_ref_without_allowed_root_passes_confinement_check(): void
    {
        $validator = $this->buildValidator(
            externalSchema: ['type' => 'integer'],
            externalRefAllowedRoot: null,
        );

        $validator->validateSchema(13, self::CLIENT_REF);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function relative_ref_inside_registered_document_resolves_via_repository_not_local_file(): void
    {
        $this->writeFile(
            $this->rootDir . '/leak.json',
            json_encode([
                'components' => [
                    'schemas' => [
                        'Shared' => ['type' => 'integer'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        $bUri = 'https://api.example.com/v1/b.json';
        $decoyUri = 'https://api.example.com/v1/leak.json';

        $documentB = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'b', 'version' => '1.0.0'],
            '$self' => $bUri,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        '$ref' => 'leak.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $decoyDocument = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'decoy', 'version' => '1.0.0'],
            '$self' => $decoyUri,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => ['type' => 'string'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $main = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            '$self' => self::MAIN_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => [
                        '$ref' => $bUri . '#/components/schemas/Shared',
                    ],
                ],
            ],
        ];

        $previousCwd = getcwd();
        chdir($this->rootDir);

        try {
            $validator = OpenApiValidatorBuilder::create()
                ->withDocument($bUri, $documentB)
                ->withDocument($decoyUri, $decoyDocument)
                ->fromJsonString(json_encode($main, JSON_THROW_ON_ERROR))
                ->build();

            $validator->validateSchema('answered-by-decoy', self::CLIENT_REF);

            $this->expectException(ValidationException::class);
            $validator->validateSchema(42, self::CLIENT_REF);
        } finally {
            if (is_string($previousCwd)) {
                chdir($previousCwd);
            }
        }
    }

    #[Test]
    public function relative_ref_inside_registered_document_without_registered_target_fails_at_build(): void
    {
        $documentB = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'b', 'version' => '1.0.0'],
            '$self' => 'https://api.example.com/v1/b.json',
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        '$ref' => 'nowhere.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(BuilderException::class);

        OpenApiValidatorBuilder::create()
            ->withDocument('https://api.example.com/v1/b.json', $documentB)
            ->fromJsonString($this->cleanMainDocumentJson())
            ->build();
    }

    #[Test]
    public function yaml_document_larger_than_spec_size_limit_is_still_confinement_scanned(): void
    {
        $yaml = <<<'YAML'
openapi: 3.1.0
info:
  title: oversized-yaml
  version: 1.0.0
paths: []
components:
  schemas:
    Shared:
      type: object
      required: [secret]
      properties:
        secret:
          $ref: 'file:///tmp/secrets.json#/components/schemas/Shared'
      description: "padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding padding"
YAML;

        $this->assertGreaterThan(600, strlen($yaml));

        try {
            OpenApiValidatorBuilder::create()
                ->withMaxSpecSize(600)
                ->withDocument('https://api.example.com/v1/shared-yaml.yaml', $yaml)
                ->fromJsonString($this->cleanMainDocumentJson())
                ->build();
            $this->fail('Expected BuilderException to be thrown');
        } catch (BuilderException $e) {
            $this->assertStringContainsString('https://api.example.com/v1/shared-yaml.yaml', $e->getMessage());
            $this->assertStringContainsString('file:///tmp/secrets.json', $e->getMessage());
        }
    }

    #[Test]
    public function relative_ref_without_self_uri_is_not_resolvable_via_repository(): void
    {
        $main = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => [
                        '$ref' => 'shared.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ];

        $this->expectException(BuilderException::class);

        OpenApiValidatorBuilder::create()
            ->withDocument(self::MAIN_URI_SHARED, $this->sharedDocumentJson(['type' => 'integer']))
            ->fromJsonString(json_encode($main, JSON_THROW_ON_ERROR))
            ->build();
    }

    #[Test]
    public function relative_ref_without_self_uri_falls_back_to_file_resolver(): void
    {
        $main = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => [
                        '$ref' => 'nowhere.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ];

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument(self::MAIN_URI_SHARED, $this->sharedDocumentJson(['type' => 'integer']))
            ->withExternalRefAllowedRoot($this->rootDir)
            ->fromJsonString(json_encode($main, JSON_THROW_ON_ERROR))
            ->build();

        $this->expectException(RuntimeException::class);
        $validator->validateSchema([], self::CLIENT_REF);
    }

    #[Test]
    public function pointer_to_missing_target_in_registered_document_is_unresolvable(): void
    {
        $validator = $this->builderWithSharedDocument(['type' => 'integer'])
            ->withExternalRefAllowedRoot($this->rootDir)
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => self::EXTERNAL_URI . '#/components/schemas/Missing',
            ]))
            ->build();

        try {
            $validator->validateSchema([], self::CLIENT_REF);
            $this->fail('Expected UnresolvableRefException to be thrown');
        } catch (UnresolvableRefException $e) {
            $this->assertStringContainsString(
                self::EXTERNAL_URI . '#/components/schemas/Missing',
                $e->ref(reveal: true),
            );
        }
    }

    #[Test]
    public function unregistered_ref_without_allowed_root_still_requires_allowed_root(): void
    {
        $this->expectException(BuilderException::class);

        OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson(['type' => 'integer']))
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => 'https://missing.example.com/nowhere.json#/components/schemas/Shared',
            ]))
            ->build();
    }

    #[Test]
    public function registered_document_with_file_ref_without_allowed_root_fails_confinement(): void
    {
        $leakingDocument = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'leaky', 'version' => '1.0.0'],
            '$self' => self::EXTERNAL_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        'type' => 'object',
                        'properties' => [
                            'secret' => ['$ref' => 'file:///tmp/secrets.json'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        try {
            OpenApiValidatorBuilder::create()
                ->withDocument(self::EXTERNAL_URI, $leakingDocument)
                ->fromJsonString($this->cleanMainDocumentJson())
                ->build();
            $this->fail('Expected BuilderException to be thrown');
        } catch (BuilderException $e) {
            $this->assertStringContainsString(self::EXTERNAL_URI, $e->getMessage());
            $this->assertStringContainsString('file:///tmp/secrets.json', $e->getMessage());
        }
    }

    #[Test]
    public function registered_document_with_unregistered_uri_ref_without_allowed_root_fails_confinement(): void
    {
        $danglingDocument = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'dangling', 'version' => '1.0.0'],
            '$self' => self::EXTERNAL_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        '$ref' => 'https://unknown.example.com/nowhere.json#/components/schemas/Shared',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(BuilderException::class);

        OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, $danglingDocument)
            ->fromJsonString($this->cleanMainDocumentJson())
            ->build();
    }

    #[Test]
    public function registered_document_refs_to_registered_documents_pass_confinement(): void
    {
        $otherUri = 'https://api.example.com/v1/two.json';

        $referrerDocument = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'referrer', 'version' => '1.0.0'],
            '$self' => self::EXTERNAL_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => [
                        'allOf' => [
                            ['$ref' => $otherUri . '#/components/schemas/Shared'],
                            ['$ref' => 'two.json#/components/schemas/Shared'],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $targetDocument = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'target', 'version' => '1.0.0'],
            '$self' => $otherUri,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => ['type' => 'string'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $main = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'main', 'version' => '1.0.0'],
            '$self' => self::MAIN_URI,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Client' => [
                        '$ref' => self::EXTERNAL_URI . '#/components/schemas/Shared',
                    ],
                ],
            ],
        ];

        $validator = OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, $referrerDocument)
            ->withDocument($otherUri, $targetDocument)
            ->fromJsonString(json_encode($main, JSON_THROW_ON_ERROR))
            ->build();

        $validator->validateSchema('resolved-through-repository', self::CLIENT_REF);

        $this->expectException(ValidationException::class);
        $validator->validateSchema(42, self::CLIENT_REF);
    }

    #[Test]
    public function confinement_cache_verdict_is_scoped_by_repository_fingerprint(): void
    {
        $cache = new SchemaCache(new Psr6ArrayCacheStub());

        $withRepository = OpenApiValidatorBuilder::create()
            ->withCache($cache)
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson(['type' => 'integer']))
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => self::EXTERNAL_URI . '#/components/schemas/Shared',
            ]))
            ->build();

        $withRepository->validateSchema(5, self::CLIENT_REF);

        $this->expectException(BuilderException::class);

        OpenApiValidatorBuilder::create()
            ->withCache($cache)
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => self::EXTERNAL_URI . '#/components/schemas/Shared',
            ]))
            ->build();
    }

    #[Test]
    public function registered_documents_oversized_against_final_cap_fail_at_build(): void
    {
        $this->expectException(ExternalRefTooLargeException::class);

        OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson(['type' => 'integer']))
            ->withExternalRefMaxBytes(64)
            ->fromJsonString($this->cleanMainDocumentJson())
            ->build();
    }

    #[Test]
    public function with_document_after_cap_tightening_throws_external_ref_too_large(): void
    {
        $this->expectException(ExternalRefTooLargeException::class);

        OpenApiValidatorBuilder::create()
            ->withDocument('https://api.example.com/v1/first.json', $this->smallDocumentJson())
            ->withExternalRefMaxBytes(64)
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson(['type' => 'integer']));
    }

    #[Test]
    public function small_document_after_cap_tightening_registers_successfully(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->withExternalRefMaxBytes(64)
            ->withDocument('https://api.example.com/v1/small.json', $this->smallDocumentJson())
            ->fromJsonString($this->cleanMainDocumentJson())
            ->build();

        $this->addToAssertionCount(1);
    }

    /**
     * @param array<array-key, mixed> $sharedSchema
     */
    private function buildValidator(
        array $externalSchema,
        ?string $externalRefAllowedRoot = null,
    ): OpenApiValidatorInterface {
        $builder = $this->builderWithSharedDocument($externalSchema);

        if (null !== $externalRefAllowedRoot) {
            $builder = $builder->withExternalRefAllowedRoot($externalRefAllowedRoot);
        }

        return $builder
            ->fromJsonString($this->mainDocumentJson([
                '$ref' => self::EXTERNAL_URI . '#/components/schemas/Shared',
            ]))
            ->build();
    }

    /**
     * @param array<array-key, mixed> $sharedSchema
     */
    private function builderWithSharedDocument(array $sharedSchema): OpenApiValidatorBuilder
    {
        return OpenApiValidatorBuilder::create()
            ->withDocument(self::EXTERNAL_URI, $this->sharedDocumentJson($sharedSchema));
    }

    /**
     * @param array<array-key, mixed> $sharedSchema
     */
    private function sharedDocumentJson(array $sharedSchema, string $selfUri = self::EXTERNAL_URI): string
    {
        return json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'shared',
                'version' => '1.0.0',
            ],
            '$self' => $selfUri,
            'paths' => [],
            'components' => [
                'schemas' => [
                    'Shared' => $sharedSchema,
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

    private function cleanMainDocumentJson(): string
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

    private function smallDocumentJson(): string
    {
        return '{"openapi":"3.1.0","info":{"title":"d","version":"1"}}';
    }

    private function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    private function removeDirectory(string $directory): void
    {
        if (false === is_dir($directory)) {
            return;
        }

        foreach (glob($directory . '/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->removeDirectory($item);

                continue;
            }

            unlink($item);
        }

        rmdir($directory);
    }
}
