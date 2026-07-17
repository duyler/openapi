<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;
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

    private OpenApiValidatorInterface $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();
    }

    #[Test]
    public function accepts_known_and_pattern_properties_with_additional_properties_false(): void
    {
        $data = [
            'name' => 'John',
            'x-custom' => 'value',
        ];

        $succeeded = false;

        try {
            $this->validator->validateSchema($data, '#/components/schemas/UserProfile');
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
    public function rejects_unknown_property_with_additional_properties_false(): void
    {
        $data = [
            'name' => 'John',
            'other' => 'value',
        ];

        $caught = null;

        try {
            $this->validator->validateSchema($data, '#/components/schemas/UserProfile');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(ValidationException::class, $caught);
        self::assertSame('Additional properties are not allowed: other', $caught->getMessage());
    }

    #[Test]
    public function rejects_missing_required_property_when_only_pattern_provided(): void
    {
        $data = [
            'x-custom' => 'value',
        ];

        $caught = null;

        try {
            $this->validator->validateSchema($data, '#/components/schemas/UserProfile');
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
    public function rejects_pattern_property_with_wrong_type(): void
    {
        $data = [
            'name' => 'John',
            'x-custom' => 123,
        ];

        $caught = null;

        try {
            $this->validator->validateSchema($data, '#/components/schemas/UserProfile');
        } catch (ValidationException $exception) {
            foreach ($exception->getErrors() as $error) {
                if ($error instanceof TypeMismatchError) {
                    $caught = $error;
                    break;
                }
            }
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
        self::assertSame('string', $caught->params()['expected']);
        self::assertSame('int', $caught->params()['actual']);
    }
}
