<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;

use function sprintf;

#[CoversClass(PropertiesValidator::class)]
class PropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PropertiesValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PropertiesValidator($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function validate_all_properties(): void
    {
        $nameSchema = new Schema(type: 'string', minLength: 2);
        $ageSchema = new Schema(type: 'integer', minimum: 18);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
                'age' => $ageSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_missing_properties(): void
    {
        $nameSchema = new Schema(type: 'string', minLength: 2);
        $ageSchema = new Schema(type: 'integer', minimum: 18);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
                'age' => $ageSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_property(): void
    {
        $nameSchema = new Schema(type: 'string', minLength: 10);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
        );

        $caught = null;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(MinLengthError::class, $errors[0]);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate('string value', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_properties_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_properties_is_empty(): void
    {
        $schema = new Schema(type: 'object', properties: []);

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_nested_object(): void
    {
        $addressSchema = new Schema(
            type: 'object',
            properties: [
                'city' => new Schema(type: 'string'),
                'zip' => new Schema(type: 'string'),
            ],
        );
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => $addressSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['address' => ['city' => 'NYC', 'zip' => '10001']], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_multiple_properties_with_different_types(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
                'active' => new Schema(type: 'boolean'),
                'score' => new Schema(type: 'number'),
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate([
                'name' => 'John',
                'age' => 30,
                'active' => true,
                'score' => 95.5,
            ], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_nested_invalid_property(): void
    {
        $addressSchema = new Schema(
            type: 'object',
            properties: [
                'city' => new Schema(type: 'string'),
                'zip' => new Schema(type: 'integer'),
            ],
        );
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => $addressSchema,
            ],
        );

        $caught = null;

        try {
            $this->validator->validate(['address' => ['city' => 'NYC', 'zip' => '10001']], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
    }

    /**
     * P-010 changed SchemaValueNormalizer to normalize stdClass to its
     * property-view array. A stdClass-typed property value therefore no
     * longer triggers InvalidDataTypeException at the normalizer; it
     * fails validation as a regular TypeMismatchError against the schema.
     */
    #[Test]
    public function validate_property_with_invalid_type_throws_meaningful_exception(): void
    {
        $this->expectException(ValidationException::class);

        $schema = new Schema(
            type: 'object',
            properties: [
                'test' => new Schema(type: 'string'),
            ],
        );

        $this->validator->validate(['test' => new stdClass()], $schema);
    }

    #[Test]
    public function validate_properties_with_additional_property(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'extra' => 'any data'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_properties_empty_object(): void
    {
        $nameSchema = new Schema(type: 'string');
        $ageSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
                'age' => $ageSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate([], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_properties_with_nullable_and_context(): void
    {
        $nameSchema = new Schema(type: 'string', nullable: true);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $succeeded = false;

        try {
            $this->validator->validate(['name' => null], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_properties_with_nullable_value(): void
    {
        $nameSchema = new Schema(type: 'string', nullable: true);
        $ageSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
                'age' => $ageSchema,
            ],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $succeeded = false;

        try {
            $this->validator->validate(['name' => null, 'age' => 30], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_properties_with_context(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_properties_with_multiple_nullable(): void
    {
        $nameSchema = new Schema(type: 'string', nullable: true);
        $ageSchema = new Schema(type: 'integer', nullable: true);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
                'age' => $ageSchema,
            ],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $succeeded = false;

        try {
            $this->validator->validate(['name' => null, 'age' => null], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
