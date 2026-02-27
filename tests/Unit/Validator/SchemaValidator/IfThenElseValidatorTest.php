<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\IfThenElseValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IfThenElseValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private IfThenElseValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new IfThenElseValidator($this->pool);
    }

    #[Test]
    public function apply_then_when_if_valid(): void
    {
        $ifSchema = new Schema(type: 'number', minimum: 0);
        $thenSchema = new Schema(type: 'number', maximum: 100);
        $schema = new Schema(
            if: $ifSchema,
            then: $thenSchema,
        );

        $this->validator->validate(50, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function apply_else_when_if_invalid(): void
    {
        $ifSchema = new Schema(type: 'number', minimum: 0);
        $elseSchema = new Schema(type: 'number', maximum: -1);
        $schema = new Schema(
            if: $ifSchema,
            else: $elseSchema,
        );

        $this->validator->validate(-5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_in_then_schema(): void
    {
        $ifSchema = new Schema(type: 'number', minimum: 0);
        $thenSchema = new Schema(type: 'number', maximum: 100);
        $schema = new Schema(
            if: $ifSchema,
            then: $thenSchema,
        );

        $this->expectException(MaximumError::class);

        $this->validator->validate(150, $schema);
    }

    #[Test]
    public function throw_error_in_else_schema(): void
    {
        $ifSchema = new Schema(type: 'number', minimum: 0);
        $elseSchema = new Schema(type: 'number', maximum: -1);
        $schema = new Schema(
            if: $ifSchema,
            else: $elseSchema,
        );

        $this->expectException(MaximumError::class);

        $this->validator->validate(-0.5, $schema);
    }

    #[Test]
    public function skip_when_if_is_null(): void
    {
        $schema = new Schema(type: 'number');

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function work_with_only_if_and_then(): void
    {
        $ifSchema = new Schema(type: 'string');
        $thenSchema = new Schema(type: 'string', minLength: 5);
        $schema = new Schema(
            if: $ifSchema,
            then: $thenSchema,
        );

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function work_with_only_if_and_else(): void
    {
        $ifSchema = new Schema(type: 'string');
        $elseSchema = new Schema(type: 'number');
        $schema = new Schema(
            if: $ifSchema,
            else: $elseSchema,
        );

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function not_apply_then_when_if_invalid(): void
    {
        $ifSchema = new Schema(type: 'number', minimum: 0);
        $thenSchema = new Schema(type: 'number', maximum: 100);
        $schema = new Schema(
            if: $ifSchema,
            then: $thenSchema,
        );

        $this->validator->validate(-5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function not_apply_else_when_if_valid(): void
    {
        $ifSchema = new Schema(type: 'number', minimum: 0);
        $elseSchema = new Schema(type: 'number', maximum: -1);
        $schema = new Schema(
            if: $ifSchema,
            else: $elseSchema,
        );

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }
}
