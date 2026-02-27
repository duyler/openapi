<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PropertyNamesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PropertyNamesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PropertyNamesValidator($this->pool);
    }

    #[Test]
    public function validate_property_names(): void
    {
        $nameSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function enforce_pattern_on_property_names(): void
    {
        $nameSchema = new Schema(type: 'string', pattern: '/^[a-z]+$/');
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_property_name(): void
    {
        $nameSchema = new Schema(type: 'string', minLength: 5);
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->expectException(MinLengthError::class);

        $this->validator->validate(['name' => 'John', 'age' => 30], $schema);
    }

    #[Test]
    public function throw_error_for_pattern_mismatch(): void
    {
        $nameSchema = new Schema(type: 'string', pattern: '/^[a-z]+$/');
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->expectException(PatternMismatchError::class);

        $this->validator->validate(['firstName' => 'John', 'age' => 30], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_property_names_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_object(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_property_names_with_max_length(): void
    {
        $nameSchema = new Schema(type: 'string', maxLength: 10);
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->validator->validate(['short' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_long_property_name(): void
    {
        $nameSchema = new Schema(type: 'string', maxLength: 5);
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->expectException(MaxLengthError::class);

        $this->validator->validate(['veryLongName' => 'value'], $schema);
    }

    #[Test]
    public function throw_error_for_invalid_regex_pattern_in_property_names(): void
    {
        $nameSchema = new Schema(type: 'string', pattern: '[invalid');
        $schema = new Schema(
            type: 'object',
            propertyNames: $nameSchema,
        );

        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "/[invalid/":');

        $this->validator->validate(['name' => 'value'], $schema);
    }
}
