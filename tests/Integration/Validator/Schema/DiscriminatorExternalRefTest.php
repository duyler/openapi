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

#[CoversClass(DiscriminatorValidator::class)]
#[CoversClass(RefResolver::class)]
final class DiscriminatorExternalRefTest extends TestCase
{
    private const string EXTERNAL_URL_MAPPING_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: External URL Mapping API
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
  title: Relative File Mapping API
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

    #[Test]
    public function di_08_external_url_mapping_throws_builder_exception_at_build_time(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('https://example.com/schemas/cat.json');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXTERNAL_URL_MAPPING_SPEC)
            ->build();
    }

    #[Test]
    public function di_08_relative_file_mapping_throws_builder_exception_at_build_time(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('schemas/cat.yaml');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RELATIVE_FILE_MAPPING_SPEC)
            ->build();
    }

    #[Test]
    public function di_08_external_mapping_with_value_absent_from_request_throws_builder_exception_at_build_time(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('https://example.com/schemas/cat.json');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXTERNAL_URL_MAPPING_SPEC)
            ->build();
    }

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

    #[Test]
    public function di_08_local_pointer_mapping_resolves_normally(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Local Mapping API
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
