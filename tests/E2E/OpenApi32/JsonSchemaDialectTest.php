<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E\OpenApi32;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * OA-04: OpenAPI 3.2 jsonSchemaDialect — declares JSON Schema dialect URL.
 *
 * Current behavior (documented): the field is parsed, stored on OpenApiDocument,
 * and round-tripped through jsonSerialize. The validator pipeline does NOT switch
 * validation rules based on the dialect value; the standard draft 2020-12
 * validator is always used. Tests pin this actual behavior.
 */
final class JsonSchemaDialectTest extends TestCase
{
    private const string JSON_SCHEMA_DIALECT_2020_12 = 'https://json-schema.org/draft/2020-12/schema';

    private const string SPEC_WITH_DIALECT = <<<'YAML'
openapi: 3.2.0
info:
  title: Dialect API
  version: 1.0.0
jsonSchemaDialect: https://json-schema.org/draft/2020-12/schema
paths: {}
components:
  schemas:
    User:
      type: object
      required:
        - name
      properties:
        name:
          type: string
        age:
          type: integer
          minimum: 0
YAML;

    private const string SPEC_WITHOUT_DIALECT = <<<'YAML'
openapi: 3.2.0
info:
  title: No Dialect API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      required:
        - name
      properties:
        name:
          type: string
        age:
          type: integer
          minimum: 0
YAML;

    #[Test]
    public function build_with_dialect_stores_value_on_document(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_DIALECT)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);
        self::assertSame(
            self::JSON_SCHEMA_DIALECT_2020_12,
            $validator->getDocument()->jsonSchemaDialect,
        );
    }

    #[Test]
    public function build_without_dialect_leaves_field_null(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITHOUT_DIALECT)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);
        self::assertNull($validator->getDocument()->jsonSchemaDialect);
    }

    #[Test]
    public function validate_schema_passes_with_dialect_set(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_DIALECT)
            ->build();

        $data = ['name' => 'Alice', 'age' => 30];

        $succeeded = false;

        try {
            $validator->validateSchema($data, '#/components/schemas/User');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass with dialect set, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_schema_rejects_invalid_data_with_dialect_set(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_DIALECT)
            ->build();

        $data = ['name' => 123];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/User');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $caught = $errors[0] ?? null;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught, 'Type mismatch must be caught even with dialect set');
        self::assertSame('type', $caught->keyword());
        self::assertSame('string', $caught->params()['expected']);
    }

    #[Test]
    public function dialect_does_not_change_validation_result_compared_to_no_dialect(): void
    {
        $withDialect = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_DIALECT)
            ->build();
        $withoutDialect = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITHOUT_DIALECT)
            ->build();

        $validData = ['name' => 'Bob', 'age' => 25];

        $withDialectSucceeded = false;
        $withoutDialectSucceeded = false;

        try {
            $withDialect->validateSchema($validData, '#/components/schemas/User');
            $withDialectSucceeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('With-dialect validation of valid data must pass, got: %s', $e->getMessage()));
        }

        try {
            $withoutDialect->validateSchema($validData, '#/components/schemas/User');
            $withoutDialectSucceeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Without-dialect validation of valid data must pass, got: %s', $e->getMessage()));
        }

        self::assertSame($withDialectSucceeded, $withoutDialectSucceeded);
    }

    #[Test]
    public function arbitrary_dialect_string_is_stored_without_affecting_validation(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Custom Dialect API
  version: 1.0.0
jsonSchemaDialect: https://example.com/schemas/custom-dialect.json
paths: {}
components:
  schemas:
    Item:
      type: object
      required:
        - id
      properties:
        id:
          type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);
        self::assertSame(
            'https://example.com/schemas/custom-dialect.json',
            $validator->getDocument()->jsonSchemaDialect,
        );

        $succeeded = false;

        try {
            $validator->validateSchema(['id' => 'abc'], '#/components/schemas/Item');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Custom dialect must not break validation, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function dialect_is_round_tripped_through_json_serialize(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC_WITH_DIALECT)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);

        $serialized = $validator->getDocument()->jsonSerialize();

        self::assertArrayHasKey('jsonSchemaDialect', $serialized);
        self::assertSame(self::JSON_SCHEMA_DIALECT_2020_12, $serialized['jsonSchemaDialect']);
    }
}
