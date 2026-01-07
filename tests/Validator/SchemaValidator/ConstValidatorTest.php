<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConstValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ConstValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ConstValidator($this->pool);
    }

    #[Test]
    public function validate_matching_const(): void
    {
        $schema = new Schema(type: 'string', const: 'fixed-value');

        $this->validator->validate('fixed-value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_mismatch(): void
    {
        $schema = new Schema(type: 'string', const: 'fixed-value');

        $this->expectException(ConstError::class);

        $this->validator->validate('different-value', $schema);
    }

    #[Test]
    public function validate_with_numeric_const(): void
    {
        $schema = new Schema(type: 'number', const: 42);

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_wrong_numeric_const(): void
    {
        $schema = new Schema(type: 'number', const: 42);

        $this->expectException(ConstError::class);

        $this->validator->validate(43, $schema);
    }

    #[Test]
    public function validate_with_boolean_const(): void
    {
        $schema = new Schema(type: 'boolean', const: true);

        $this->validator->validate(true, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_wrong_boolean_const(): void
    {
        $schema = new Schema(type: 'boolean', const: true);

        $this->expectException(ConstError::class);

        $this->validator->validate(false, $schema);
    }

    #[Test]
    public function validate_with_array_const(): void
    {
        $schema = new Schema(
            type: 'array',
            const: [1, 2, 3],
        );

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_different_array_const(): void
    {
        $schema = new Schema(
            type: 'array',
            const: [1, 2, 3],
        );

        $this->expectException(ConstError::class);

        $this->validator->validate([1, 2, 4], $schema);
    }

    #[Test]
    public function validate_with_object_const(): void
    {
        $schema = new Schema(
            type: 'object',
            const: ['key' => 'value'],
        );

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_different_object_const(): void
    {
        $schema = new Schema(
            type: 'object',
            const: ['key' => 'value'],
        );

        $this->expectException(ConstError::class);

        $this->validator->validate(['key' => 'different'], $schema);
    }

    #[Test]
    public function use_strict_comparison(): void
    {
        $schema = new Schema(type: 'number', const: 42);

        $this->expectException(ConstError::class);

        $this->validator->validate('42', $schema);
    }

    #[Test]
    public function use_strict_comparison_for_boolean(): void
    {
        $schema = new Schema(type: 'boolean', const: true);

        $this->expectException(ConstError::class);

        $this->validator->validate(1, $schema);
    }
}
