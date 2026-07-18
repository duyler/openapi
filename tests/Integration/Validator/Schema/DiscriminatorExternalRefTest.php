<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Schema\DiscriminatorValidator;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use RuntimeException;

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
     * Negative: mapping with external https URL is not supported by the
     * builtin FileExternalRefResolver.
     *
     * Actual behaviour characterization: DiscriminatorValidator delegates to
     * RefResolver::resolve(), which delegates to the builtin
     * FileExternalRefResolver for non-local refs. The builtin resolver allows only
     * file:// URIs and scheme-less relative paths; every non-allowlisted scheme
     * (http, https, ftp, php, phar, data, etc.) is rejected with
     * ExternalRefSecurityException, which RefResolver wraps as
     * UnresolvableRefException with a clear "inject custom resolver" hint.
     */
    #[Test]
    public function di_08_external_url_mapping_throws_unresolvable_ref_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXTERNAL_URL_MAPPING_SPEC)
            ->build();

        $catData = ['petType' => 'cat', 'name' => 'Tom'];

        $caught = null;

        try {
            $validator->validateSchema($catData, '#/components/schemas/Pet');
        } catch (UnresolvableRefException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Expected UnresolvableRefException for external URL mapping value.',
        );

        $this->assertSame('https://example.com/schemas/cat.json', $caught->ref);
        $this->assertStringContainsString('External ref not resolved', $caught->reason);
    }

    /**
     * Negative: relative file path mapping value (without $self) is resolved
     * by the builtin FileExternalRefResolver against CWD. When the referenced
     * file does not exist relative to CWD, RuntimeException is raised.
     */
    #[Test]
    public function di_08_relative_file_mapping_throws_runtime_exception_for_missing_file(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::RELATIVE_FILE_MAPPING_SPEC)
            ->build();

        $dogData = ['petType' => 'dog', 'name' => 'Rex', 'breed' => 'labrador'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('External ref file not found');

        $validator->validateSchema($dogData, '#/components/schemas/Pet');
    }

    /**
     * Positive characterization: when mapping value is an external URL, but
     * the discriminator value is not in the mapping, the validator scans
     * oneOf schemas. If no candidate matches the discriminator value, the
     * external ref is never dereferenced — instead UnknownDiscriminatorValueException
     * is raised. This documents that external refs in mapping only fail
     * when actually used.
     */
    #[Test]
    public function di_08_external_mapping_not_triggered_when_value_absent_from_mapping(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::EXTERNAL_URL_MAPPING_SPEC)
            ->build();

        $data = ['petType' => 'bird'];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
        } catch (UnknownDiscriminatorValueException $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Unknown discriminator value not in mapping must raise UnknownDiscriminatorValueException.',
        );

        $this->assertStringContainsString('bird', $caught->getMessage());
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
