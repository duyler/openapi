<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Schema\DiscriminatorValidator;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * DI-08: Discriminator mapping with external $ref.
 *
 * OpenAPI 3.2 spec allows discriminator mapping values to be either local
 * pointers ('#/components/schemas/Foo') or external references (URIs, file
 * paths). This codebase only supports local pointer refs; external refs are
 * rejected by RefResolver.
 */
#[CoversClass(DiscriminatorValidator::class)]
#[CoversClass(RefResolver::class)]
final class DiscriminatorExternalRefTest extends TestCase
{
    private const string EXTERNAL_URL_MAPPING_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: DI-08 External URL Mapping API
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required: [petType]
      properties:
        petType:
          type: string
      discriminator:
        propertyName: petType
        mapping:
          cat: 'https://example.com/schemas/cat.json'
          dog: 'https://example.com/schemas/dog.json'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required: [petType, name]
      properties:
        petType:
          type: string
          enum: [cat]
        name:
          type: string
    Dog:
      type: object
      required: [petType, name, breed]
      properties:
        petType:
          type: string
          enum: [dog]
        name:
          type: string
        breed:
          type: string
YAML;

    private const string RELATIVE_FILE_MAPPING_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: DI-08 Relative File Mapping API
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required: [petType]
      properties:
        petType:
          type: string
      discriminator:
        propertyName: petType
        mapping:
          cat: 'schemas/cat.yaml'
          dog: 'schemas/dog.yaml'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required: [petType, name]
      properties:
        petType:
          type: string
          enum: [cat]
        name:
          type: string
    Dog:
      type: object
      required: [petType, name, breed]
      properties:
        petType:
          type: string
          enum: [dog]
        name:
          type: string
        breed:
          type: string
YAML;

    /**
     * Negative: a string-loaded spec whose discriminator mapping
     * references an external https URL is rejected at `build()` time
     * with `BuilderException` (STRING-SPEC-FAILOPEN fail-closed
     * behaviour). The build never reaches `DiscriminatorValidator`,
     * so no `UnresolvableRefException` is thrown; callers must remove
     * the external mapping or load the spec via `fromYamlFile` /
     * `withExternalRefAllowedRoot()` to opt into path-confinement.
     */
    #[Test]
    public function di_08_external_url_mapping_throws_builder_exception_at_build_time(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('https://example.com/schemas/cat.json');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXTERNAL_URL_MAPPING_SPEC)
            ->build();
    }

    /**
     * Negative: a string-loaded spec whose discriminator mapping
     * references a relative file path is rejected at `build()` time
     * with `BuilderException` (STRING-SPEC-FAILOPEN fail-closed
     * behaviour). The build never reaches the discriminator
     * resolution path that previously produced a `RuntimeException`
     * when the referenced file did not exist relative to CWD; callers
     * must remove the external mapping or load the spec via
     * `fromYamlFile` / `withExternalRefAllowedRoot()` to opt into
     * path-confinement.
     */
    #[Test]
    public function di_08_relative_file_mapping_throws_builder_exception_at_build_time(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('schemas/cat.yaml');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RELATIVE_FILE_MAPPING_SPEC)
            ->build();
    }

    /**
     * Negative: a string-loaded spec whose discriminator mapping
     * contains an external URL is rejected at `build()` time with
     * `BuilderException` (STRING-SPEC-FAILOPEN fail-closed
     * behaviour) — even when the discriminator value eventually used
     * at validation time would not have matched the external mapping
     * key. The guard fails closed at build time and never reaches the
     * previously-documented fail-late path where
     * `UnknownDiscriminatorValueException` would have surfaced for
     * absent values; callers must remove the external mapping or
     * load the spec via `fromYamlFile` /
     * `withExternalRefAllowedRoot()` to opt into path-confinement.
     */
    #[Test]
    public function di_08_external_mapping_with_value_absent_from_request_throws_builder_exception_at_build_time(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('https://example.com/schemas/cat.json');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXTERNAL_URL_MAPPING_SPEC)
            ->build();
    }

    /**
     * RefResolutionException is thrown by RefResolver::resolveRelativeRef()
     * when a relative reference cannot be resolved due to missing document
     * $self. This is a different code path from external URL refs (which
     * throw UnresolvableRefException).
     */
    #[Test]
    public function di_08_resolve_relative_ref_without_self_throws_ref_resolution_exception(): void
    {
        $resolver = new RefResolver();
        $document = new OpenApiDocument('3.2.0', new InfoObject('Test', '1.0.0'));

        $caught = null;

        try {
            $resolver->resolveRelativeRef('schemas/cat.yaml', $document);
        } catch (RefResolutionException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Expected RefResolutionException when resolving relative ref without document $self.',
        );

        $this->assertStringContainsString(
            'schemas/cat.yaml',
            $caught->getMessage(),
            'Exception message must reference the offending ref.',
        );
        $this->assertStringContainsString(
            '$self',
            $caught->getMessage(),
            'Exception message must mention the missing $self base URI.',
        );
    }

    /**
     * RefResolutionException is NOT thrown when document $self is set: the
     * relative ref is combined into an absolute URI string. This documents
     * that no actual external fetching happens — only URI composition.
     */
    #[Test]
    public function di_08_resolve_relative_ref_with_self_combines_uris(): void
    {
        $resolver = new RefResolver();
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Test', '1.0.0'),
            self: 'https://api.example.com/schemas/main.json',
        );

        $resolved = $resolver->resolveRelativeRef('schemas/cat.yaml', $document);

        $this->assertSame(
            'https://api.example.com/schemas/schemas/cat.yaml',
            $resolved,
            'Relative ref must be combined with $self base URI via dirname + relative path.',
        );
    }

    /**
     * Sanity check: local pointer mapping ('#/components/schemas/...') works
     * as the only supported discriminator mapping shape.
     */
    #[Test]
    public function di_08_local_pointer_mapping_resolves_normally(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: DI-08 Local Mapping API
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required: [petType]
      properties:
        petType:
          type: string
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
      oneOf:
        - $ref: '#/components/schemas/Cat'
    Cat:
      type: object
      required: [petType, name]
      properties:
        petType:
          type: string
          enum: [cat]
        name:
          type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $catData = ['petType' => 'cat', 'name' => 'Tom'];

        $caught = null;
        $succeeded = false;

        try {
            $validator->validateSchema($catData, '#/components/schemas/Pet');
            $succeeded = true;
        } catch (UnresolvableRefException $e) {
            $caught = $e;
        }

        $this->assertNull(
            $caught,
            sprintf('Local pointer mapping must not raise UnresolvableRefException, got: %s', $caught?->getMessage() ?? ''),
        );
        $this->assertTrue($succeeded);
    }
}
