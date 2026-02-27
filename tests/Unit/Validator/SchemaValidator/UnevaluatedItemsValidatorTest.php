<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use Duyler\OpenApi\Validator\Error\ValidationContext;

class UnevaluatedItemsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private UnevaluatedItemsValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new UnevaluatedItemsValidator($this->pool);
    }

    #[Test]
    public function validate_unevaluated_items(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $unevaluatedSchema = new Schema(type: 'string', minLength: 2);
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['hello', 42, 'ab', 'cd'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function work_with_prefix_items(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['hello', 42, 'extra', 'items'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_unevaluated_item(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $unevaluatedSchema = new Schema(type: 'string', minLength: 5);
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->expectException(MinLengthError::class);

        $this->validator->validate(['hello', 42, 'ab'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_unevaluated_items_is_null(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
        );

        $this->validator->validate(['hello', 42, 'extra'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_items_schema(): void
    {
        $itemsSchema = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemsSchema,
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['a', 'b', 'c'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_array(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_fewer_items_than_prefix_items(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['hello'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unevaluated_items_no_additional(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $unevaluatedSchema = new Schema(type: 'string', minLength: 2);
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['hello', 42], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unevaluated_items_without_prefix_items_or_items(): void
    {
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['a', 'b', 'c'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unevaluated_items_with_context(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
            unevaluatedItems: $unevaluatedSchema,
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $this->validator->validate(['hello', 42, 43], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unevaluated_items_all_evaluated(): void
    {
        $itemsSchema = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemsSchema,
            unevaluatedItems: $unevaluatedSchema,
        );

        $this->validator->validate(['a', 'b', 'c'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
