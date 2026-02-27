<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NumericRangeValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private NumericRangeValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new NumericRangeValidator($this->pool);
    }

    #[Test]
    public function validate_minimum(): void
    {
        $schema = new Schema(type: 'number', minimum: 5);

        $this->validator->validate(10, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_maximum(): void
    {
        $schema = new Schema(type: 'number', maximum: 10);

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_exclusive_minimum(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 5);

        $this->validator->validate(6, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_exclusive_maximum(): void
    {
        $schema = new Schema(type: 'number', exclusiveMaximum: 10);

        $this->validator->validate(9, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_multiple_of(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);

        $this->validator->validate(10, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_minimum_error(): void
    {
        $schema = new Schema(type: 'number', minimum: 5);

        $this->expectException(MinimumError::class);

        $this->validator->validate(3, $schema);
    }

    #[Test]
    public function throw_maximum_error(): void
    {
        $schema = new Schema(type: 'number', maximum: 10);

        $this->expectException(MaximumError::class);

        $this->validator->validate(15, $schema);
    }

    #[Test]
    public function throw_exclusive_minimum_error(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 5);

        $this->expectException(MinimumError::class);

        $this->validator->validate(5, $schema);
    }

    #[Test]
    public function throw_exclusive_maximum_error(): void
    {
        $schema = new Schema(type: 'number', exclusiveMaximum: 10);

        $this->expectException(MaximumError::class);

        $this->validator->validate(10, $schema);
    }

    #[Test]
    public function throw_multiple_of_error(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);

        $this->expectException(MultipleOfKeywordError::class);

        $this->validator->validate(7, $schema);
    }

    #[Test]
    public function skip_validation_for_non_numeric(): void
    {
        $schema = new Schema(type: 'string', minimum: 5);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_integer_type(): void
    {
        $schema = new Schema(type: 'integer', minimum: 1, maximum: 10);

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_float_type(): void
    {
        $schema = new Schema(type: 'number', minimum: 1.5, maximum: 10.5);

        $this->validator->validate(5.5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_boundary_values(): void
    {
        $schema = new Schema(type: 'number', minimum: 0, maximum: 100);

        $this->validator->validate(0, $schema);
        $this->validator->validate(100, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_multiple_of_with_float(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.5);

        $this->validator->validate(2.5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_multiple_of_is_zero(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.0);

        $this->validator->validate(7, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_no_constraints(): void
    {
        $schema = new Schema(type: 'number');

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }
}
