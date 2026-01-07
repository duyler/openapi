<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArrayLengthValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ArrayLengthValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ArrayLengthValidator($this->pool);
    }

    #[Test]
    public function validate_min_items(): void
    {
        $schema = new Schema(type: 'array', minItems: 2);

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_max_items(): void
    {
        $schema = new Schema(type: 'array', maxItems: 5);

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_both_min_and_max(): void
    {
        $schema = new Schema(type: 'array', minItems: 2, maxItems: 5);

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_min_items_error(): void
    {
        $schema = new Schema(type: 'array', minItems: 3);

        $this->expectException(MinItemsError::class);

        $this->validator->validate([1, 2], $schema);
    }

    #[Test]
    public function throw_max_items_error(): void
    {
        $schema = new Schema(type: 'array', maxItems: 2);

        $this->expectException(MaxItemsError::class);

        $this->validator->validate([1, 2, 3], $schema);
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $schema = new Schema(type: 'string', minItems: 3);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unique_items(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $this->validator->validate([1, 2, 3, 4], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_duplicate_items(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $this->expectException(MaxItemsError::class);

        $this->validator->validate([1, 2, 2, 3], $schema);
    }

    #[Test]
    public function validate_empty_array(): void
    {
        $schema = new Schema(type: 'array', minItems: 0);

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_array_when_min_greater_than_zero(): void
    {
        $schema = new Schema(type: 'array', minItems: 1);

        $this->expectException(MinItemsError::class);

        $this->validator->validate([], $schema);
    }

    #[Test]
    public function skip_when_no_constraints(): void
    {
        $schema = new Schema(type: 'array');

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unique_items_with_strings(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $this->validator->validate(['a', 'b', 'c'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_duplicate_strings(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $this->expectException(MaxItemsError::class);

        $this->validator->validate(['a', 'b', 'a'], $schema);
    }

    #[Test]
    public function skip_unique_items_validation_when_false(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: false);

        $this->validator->validate([1, 2, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_boundary_values(): void
    {
        $schema = new Schema(type: 'array', minItems: 1, maxItems: 3);

        $this->validator->validate([1], $schema);
        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }
}
