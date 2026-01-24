<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class ItemsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ItemsValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ItemsValidator($this->pool);
    }

    #[Test]
    public function validate_all_items(): void
    {
        $itemSchema = new Schema(type: 'string', minLength: 2);
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->validator->validate(['ab', 'abc', 'abcd'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_array(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_item(): void
    {
        $itemSchema = new Schema(type: 'string', minLength: 5);
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->expectException(MinLengthError::class);

        $this->validator->validate(['ab'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_associative_array(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_items_is_null(): void
    {
        $schema = new Schema(type: 'array');

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_numeric_items(): void
    {
        $itemSchema = new Schema(type: 'number', minimum: 0, maximum: 100);
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->validator->validate([10, 20, 30, 40, 50], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_numeric_item(): void
    {
        $itemSchema = new Schema(type: 'number', minimum: 0, maximum: 100);
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->expectException(MaximumError::class);

        $this->validator->validate([10, 150, 30], $schema);
    }

    #[Test]
    public function validate_complex_item_schema(): void
    {
        $itemSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'value' => new Schema(type: 'number'),
            ],
        );
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->validator->validate([
            ['name' => 'a', 'value' => 1],
            ['name' => 'b', 'value' => 2],
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_items_throws_exception_for_invalid_element(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([new stdClass()], $schema);
    }
}
