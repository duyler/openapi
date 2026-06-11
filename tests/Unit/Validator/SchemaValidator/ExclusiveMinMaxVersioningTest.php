<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExclusiveMinMaxVersioningTest extends TestCase
{
    private ValidatorPool $pool;
    private NumericRangeValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new NumericRangeValidator($this->pool);
    }

    #[Test]
    public function openapi_30_exclusive_minimum_true_rejects_boundary_value(): void
    {
        $schema = new Schema(
            type: 'integer',
            minimum: 5,
            exclusiveMinimum: 5.0,
        );

        $this->expectException(MinimumError::class);
        $this->validator->validate(5, $schema);
    }

    #[Test]
    public function openapi_30_exclusive_minimum_true_accepts_above_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            minimum: 5,
            exclusiveMinimum: 5.0,
        );

        $this->validator->validate(6, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_31_exclusive_minimum_number_rejects_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            exclusiveMinimum: 5.0,
        );

        $this->expectException(MinimumError::class);
        $this->validator->validate(5, $schema);
    }

    #[Test]
    public function openapi_31_exclusive_minimum_number_rejects_below_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            exclusiveMinimum: 5.0,
        );

        $this->expectException(MinimumError::class);
        $this->validator->validate(4, $schema);
    }

    #[Test]
    public function openapi_31_exclusive_minimum_number_accepts_above_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            exclusiveMinimum: 5.0,
        );

        $this->validator->validate(6, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_30_exclusive_maximum_true_rejects_boundary_value(): void
    {
        $schema = new Schema(
            type: 'integer',
            maximum: 10,
            exclusiveMaximum: 10.0,
        );

        $this->expectException(MaximumError::class);
        $this->validator->validate(10, $schema);
    }

    #[Test]
    public function openapi_30_exclusive_maximum_true_accepts_below_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            maximum: 10,
            exclusiveMaximum: 10.0,
        );

        $this->validator->validate(9, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function openapi_31_exclusive_maximum_number_rejects_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            exclusiveMaximum: 10.0,
        );

        $this->expectException(MaximumError::class);
        $this->validator->validate(10, $schema);
    }

    #[Test]
    public function openapi_31_exclusive_maximum_number_accepts_below_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            exclusiveMaximum: 10.0,
        );

        $this->validator->validate(9, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function regular_minimum_without_exclusive_accepts_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            minimum: 5,
        );

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function regular_maximum_without_exclusive_accepts_boundary(): void
    {
        $schema = new Schema(
            type: 'integer',
            maximum: 10,
        );

        $this->validator->validate(10, $schema);

        $this->expectNotToPerformAssertions();
    }
}
