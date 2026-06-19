<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SchemaValidatorAdapterContextTest extends TestCase
{
    #[Test]
    public function validate_schema_with_disabled_nullable_as_type_rejects_null_for_nullable_field(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec())
            ->disableNullableAsType()
            ->build();

        // PropertiesValidator wraps the inner null-rejection error into ValidationException rather than TypeMismatchError.
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/name/i');

        $validator->validateSchema(['name' => null], '#/components/schemas/NullableUser');
    }

    #[Test]
    public function validate_schema_with_default_nullable_allows_null_for_nullable_field(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec())
            ->build();

        $validator->validateSchema(['name' => null], '#/components/schemas/NullableUser');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_schema_with_prefer_object_strategy_accepts_empty_array_for_object(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec())
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        $validator->validateSchema([], '#/components/schemas/BagObject');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_schema_with_prefer_object_strategy_rejects_empty_array_for_array(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec())
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();

        // BagArray has no properties, so TypeValidator throws TypeMismatchError directly without PropertiesValidator wrapping.
        $this->expectException(TypeMismatchError::class);

        $validator->validateSchema([], '#/components/schemas/BagArray');
    }

    #[Test]
    public function validate_schema_with_default_strategy_accepts_empty_array_for_both_types(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec())
            ->build();

        $validator->validateSchema([], '#/components/schemas/BagObject');
        $validator->validateSchema([], '#/components/schemas/BagArray');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_schema_with_discriminator_path_still_validates(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec())
            ->disableNullableAsType()
            ->build();

        $validator->validateSchema(['petType' => 'cat', 'name' => 'Fluffy'], '#/components/schemas/Pet');

        $this->expectNotToPerformAssertions();
    }

    private function buildSpec(): string
    {
        return <<<'YAML'
openapi: 3.2.0
info:
  title: EI-009 regression
  version: 1.0.0
paths: {}
components:
  schemas:
    NullableUser:
      type: object
      properties:
        name:
          type: string
          nullable: true
    BagObject:
      type: object
    BagArray:
      type: array
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
          enum: [cat]
        name:
          type: string
YAML;
    }
}
