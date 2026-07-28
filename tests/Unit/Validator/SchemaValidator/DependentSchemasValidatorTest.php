<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\NestedValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const NAN;

#[CoversClass(DependentSchemasValidator::class)]
class DependentSchemasValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private DependentSchemasValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new DependentSchemasValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function apply_dependent_schema_when_property_present(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['billingAddress'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => $dependentSchema,
            ],
        );

        $this->validator->validate(['creditCard' => '1234567890', 'billingAddress' => '123 Main St'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_property_absent(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['billingAddress'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => $dependentSchema,
            ],
        );

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_dependent_schema_fails(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['billingAddress'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => $dependentSchema,
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['creditCard' => '1234567890'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $dependentSchema = new Schema(type: 'object');
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'key' => $dependentSchema,
            ],
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_schemas_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_dependent_schemas_is_empty(): void
    {
        $schema = new Schema(type: 'object', dependentSchemas: []);

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_multiple_dependent_schemas(): void
    {
        $dependentSchema1 = new Schema(
            type: 'object',
            required: ['field1'],
        );
        $dependentSchema2 = new Schema(
            type: 'object',
            required: ['field2'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'key1' => $dependentSchema1,
                'key2' => $dependentSchema2,
            ],
        );

        $this->validator->validate([
            'key1' => 'value1',
            'key2' => 'value2',
            'field1' => 'value1',
            'field2' => 'value2',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_only_matching_dependent_schemas(): void
    {
        $dependentSchema1 = new Schema(
            type: 'object',
            required: ['field1'],
        );
        $dependentSchema2 = new Schema(
            type: 'object',
            required: ['field2'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'key1' => $dependentSchema1,
                'key2' => $dependentSchema2,
            ],
        );

        $this->validator->validate([
            'key1' => 'value1',
            'field1' => 'value1',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function catch_invalid_data_type_in_nested_property(): void
    {
        $resource = fopen('php://memory', 'r');

        $dependentSchema = new Schema(
            type: 'object',
            properties: [
                'nested' => new Schema(type: 'string'),
            ],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'trigger' => $dependentSchema,
            ],
        );

        $this->expectException(ValidationException::class);

        try {
            $this->validator->validate([
                'trigger' => 'active',
                'nested' => $resource,
            ], $schema);
        } finally {
            fclose($resource);
        }
    }

    #[Test]
    public function nested_validator_throwing_plain_validation_exception_is_wrapped_in_nested_validation_error(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(
                    oneOf: [
                        new Schema(minimum: 0),
                        new Schema(maximum: 100),
                    ],
                ),
            ],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'trigger' => $dependentSchema,
            ],
        );

        try {
            $this->validator->validate([
                'trigger' => 'active',
                'value' => NAN,
            ], $schema);
            self::fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            self::assertCount(1, $errors);
            self::assertInstanceOf(NestedValidationError::class, $errors[0]);
        }
    }

    #[Test]
    public function rethrow_invalid_format_exception_from_dependent_schema_without_wrapping(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            properties: [
                'email' => new Schema(type: 'string', format: 'email'),
            ],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: ['trigger' => $dependentSchema],
        );

        $this->expectException(InvalidFormatException::class);

        $this->validator->validate([
            'trigger' => 'active',
            'email' => 'not-an-email',
        ], $schema);
    }

    #[Test]
    public function wrap_abstract_validation_error_from_dependent_schema_branch(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 5),
            ],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: ['trigger' => $dependentSchema],
        );

        try {
            $this->validator->validate([
                'trigger' => 'active',
                'name' => 'abc',
            ], $schema);
            self::fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            self::assertCount(1, $errors);
            self::assertInstanceOf(MinLengthError::class, $errors[0]);
            self::assertStringContainsString('validation failed', $e->getMessage());
        }
    }

    #[Test]
    public function apply_dependent_schema_with_nullable_property_inside_accepts_null_value(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            properties: [
                'optional' => new Schema(type: ['string', 'null']),
            ],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: ['trigger' => $dependentSchema],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validate([
            'trigger' => 'active',
            'optional' => null,
        ], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_dependent_schema_declared_nullable_via_nullable_flag(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            nullable: true,
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: ['trigger' => $dependentSchema],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validate(['trigger' => 'active'], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_dependent_schema_with_nested_composition_any_of_branch(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(
                    anyOf: [
                        new Schema(type: 'integer'),
                        new Schema(type: 'string'),
                    ],
                ),
            ],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: ['trigger' => $dependentSchema],
        );

        $this->validator->validate([
            'trigger' => 'active',
            'value' => 'hello',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validation_exception_with_existing_errors_passes_them_through_unchanged(): void
    {
        $dependentSchema = new Schema(
            type: 'object',
            required: ['missing1', 'missing2'],
        );
        $schema = new Schema(
            type: 'object',
            dependentSchemas: ['trigger' => $dependentSchema],
        );

        try {
            $this->validator->validate(['trigger' => 'active'], $schema);
            self::fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            self::assertStringContainsString('validation failed', $e->getMessage());
            self::assertNotEmpty($e->getErrors());
        }
    }
}
