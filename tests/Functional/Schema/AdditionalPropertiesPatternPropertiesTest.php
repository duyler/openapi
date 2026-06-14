<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class AdditionalPropertiesPatternPropertiesTest extends TestCase
{
    private const string SCHEMA_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    UserProfile:
      type: object
      properties:
        name:
          type: string
      required:
        - name
      patternProperties:
        '^x-':
          type: string
      additionalProperties: false
YAML;

    #[Test]
    public function accepts_property_from_properties_and_pattern_property_with_additional_properties_false(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'name' => 'John',
            'x-custom' => 'value',
        ];

        $succeeded = false;

        try {
            $validator->validateSchema($data, '#/components/schemas/UserProfile');
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf(
                'Expected validation to pass (name from properties, x-custom from patternProperties), got: %s',
                $e->getMessage(),
            ));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function rejects_property_not_in_properties_nor_patternProperties_with_additional_properties_false(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'name' => 'John',
            'other' => 'value',
        ];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/UserProfile');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(ValidationException::class, $caught);
        self::assertStringContainsString('other', $caught->getMessage());
    }

    #[Test]
    public function rejects_missing_required_property_when_only_patternProperty_provided(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'x-custom' => 'value',
        ];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/UserProfile');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(ValidationException::class, $caught);

        $errors = $caught->getErrors();
        self::assertCount(1, $errors);

        $error = $errors[0];
        self::assertInstanceOf(RequiredError::class, $error);
        self::assertSame('required', $error->keyword());
        self::assertSame('name', $error->params()['property']);
    }

    #[Test]
    public function rejects_pattern_property_value_with_wrong_type(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'name' => 'John',
            'x-custom' => 123,
        ];

        $caught = null;

        try {
            $validator->validateSchema($data, '#/components/schemas/UserProfile');
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
        self::assertSame('string', $caught->params()['expected']);
        self::assertSame('integer', $caught->params()['actual']);
    }
}
