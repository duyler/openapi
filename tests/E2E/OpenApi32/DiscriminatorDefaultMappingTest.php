<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E\OpenApi32;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * OA-03: OpenAPI 3.2 discriminator.defaultMapping — unmapped discriminator values
 * resolve to schema referenced by defaultMapping instead of throwing.
 */
final class DiscriminatorDefaultMappingTest extends TestCase
{
    private const string DISCRIMINATOR_WITH_DEFAULT_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Discriminator Default Mapping API
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required:
        - petType
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
          dog: '#/components/schemas/Dog'
        defaultMapping: '#/components/schemas/DefaultPet'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required:
        - petType
        - name
        - meow
      properties:
        petType:
          type: string
        name:
          type: string
        meow:
          type: boolean
    Dog:
      type: object
      required:
        - petType
        - name
        - bark
      properties:
        petType:
          type: string
        name:
          type: string
        bark:
          type: boolean
    DefaultPet:
      type: object
      required:
        - petType
        - fallbackTrait
      properties:
        petType:
          type: string
        fallbackTrait:
          type: string
YAML;

    private const string DISCRIMINATOR_WITHOUT_DEFAULT_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Discriminator Without Default API
  version: 1.0.0
paths: {}
components:
  schemas:
    Pet:
      type: object
      required:
        - petType
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
      oneOf:
        - $ref: '#/components/schemas/Cat'
    Cat:
      type: object
      required:
        - petType
        - name
      properties:
        petType:
          type: string
        name:
          type: string
YAML;

    #[Test]
    public function mapped_value_cat_resolves_to_cat_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITH_DEFAULT_SPEC)
            ->build();

        $data = [
            'petType' => 'cat',
            'name' => 'Whiskers',
            'meow' => true,
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected cat data to validate against Cat schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unmapped_value_falls_back_to_default_mapping_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITH_DEFAULT_SPEC)
            ->build();

        $data = [
            'petType' => 'unknown',
            'fallbackTrait' => 'mysterious',
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected unmapped data to validate against DefaultPet schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unmapped_value_validates_default_mapping_required_fields(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITH_DEFAULT_SPEC)
            ->build();

        $data = [
            'petType' => 'bird',
        ];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Unmapped discriminator with missing default-schema required field must fail');
        self::assertSame('required', $caught->getErrors()[0]->keyword());
        self::assertStringContainsString('fallbackTrait', $caught->getErrors()[0]->message());
    }

    #[Test]
    public function mapped_value_with_invalid_data_rejects_via_cat_schema_constraints(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITH_DEFAULT_SPEC)
            ->build();

        $data = [
            'petType' => 'cat',
            'name' => 'Whiskers',
        ];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Cat data missing required "meow" must fail');
        self::assertSame('required', $caught->getErrors()[0]->keyword());
        self::assertStringContainsString('meow', $caught->getErrors()[0]->message());
    }

    #[Test]
    public function discriminator_without_default_mapping_throws_unknown_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITHOUT_DEFAULT_SPEC)
            ->build();

        $data = ['petType' => 'elephant'];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
        } catch (UnknownDiscriminatorValueException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Unknown discriminator without defaultMapping must throw');
        self::assertSame('discriminator', $caught->keyword());
        self::assertSame('elephant', $caught->params()['value']);
    }

    #[Test]
    public function unmapped_value_with_valid_fallback_data_validates_via_default_mapping(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITH_DEFAULT_SPEC)
            ->build();

        $data = ['petType' => 'dragon', 'fallbackTrait' => 'fire-breathing'];

        $succeeded = false;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected dragon data to validate via defaultMapping, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unmapped_value_missing_default_mapping_required_field_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DISCRIMINATOR_WITH_DEFAULT_SPEC)
            ->build();

        $data = ['petType' => 'dragon'];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/Pet');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Dragon data missing fallbackTrait must fail');
        self::assertSame('required', $caught->getErrors()[0]->keyword());
    }
}
