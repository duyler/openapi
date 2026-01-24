<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Tests\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class PropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PropertiesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PropertiesValidator($this->pool);
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

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->expectException(MinLengthError::class);

        $this->validator->validate(['name' => 'John'], $schema);
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

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_properties_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_properties_is_empty(): void
    {
        $schema = new Schema(type: 'object', properties: []);

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['address' => ['city' => 'NYC', 'zip' => '10001']], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate([
            'name' => 'John',
            'age' => 30,
            'active' => true,
            'score' => 95.5,
        ], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(['address' => ['city' => 'NYC', 'zip' => '10001']], $schema);
    }

    #[Test]
    public function validate_property_with_invalid_type_throws_meaningful_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Property "test" has invalid data type');

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

        $this->validator->validate(['name' => 'John', 'extra' => 'any data'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }
}
