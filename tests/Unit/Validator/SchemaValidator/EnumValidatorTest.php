<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\EnumValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private EnumValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new EnumValidator($this->pool);
    }

    #[Test]
    public function validate_enum_value(): void
    {
        $schema = new Schema(
            type: 'string',
            enum: ['red', 'green', 'blue'],
        );

        $this->validator->validate('red', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_value(): void
    {
        $schema = new Schema(
            type: 'string',
            enum: ['red', 'green', 'blue'],
        );

        $this->expectException(EnumError::class);

        $this->validator->validate('yellow', $schema);
    }

    #[Test]
    public function skip_when_enum_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_enum_is_empty(): void
    {
        $schema = new Schema(type: 'string', enum: []);

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_numeric_enum(): void
    {
        $schema = new Schema(
            type: 'number',
            enum: [1, 2, 3, 5, 8],
        );

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_numeric_value(): void
    {
        $schema = new Schema(
            type: 'number',
            enum: [1, 2, 3],
        );

        $this->expectException(EnumError::class);

        $this->validator->validate(4, $schema);
    }

    #[Test]
    public function validate_with_mixed_types(): void
    {
        $schema = new Schema(
            enum: ['string', 42, true, null],
        );

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_mixed_type_mismatch(): void
    {
        $schema = new Schema(
            enum: ['string', 42, true, null],
        );

        $this->expectException(EnumError::class);

        $this->validator->validate(false, $schema);
    }

    #[Test]
    public function validate_with_boolean_enum(): void
    {
        $schema = new Schema(
            type: 'boolean',
            enum: [true, false],
        );

        $this->validator->validate(true, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_null_in_enum(): void
    {
        $schema = new Schema(
            enum: ['value', null],
        );

        $this->validator->validate(null, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function use_strict_type_checking(): void
    {
        $schema = new Schema(
            enum: [1, 2, 3],
        );

        $this->expectException(EnumError::class);

        $this->validator->validate('1', $schema);
    }

    #[Test]
    public function use_strict_checking_for_boolean(): void
    {
        $schema = new Schema(
            enum: [true],
        );

        $this->expectException(EnumError::class);

        $this->validator->validate(1, $schema);
    }

    #[Test]
    public function validate_single_value_enum(): void
    {
        $schema = new Schema(
            type: 'string',
            enum: ['only-value'],
        );

        $this->validator->validate('only-value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_single_value_enum_mismatch(): void
    {
        $schema = new Schema(
            type: 'string',
            enum: ['only-value'],
        );

        $this->expectException(EnumError::class);

        $this->validator->validate('different', $schema);
    }
}
