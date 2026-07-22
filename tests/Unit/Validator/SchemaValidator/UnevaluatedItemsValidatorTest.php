<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use Duyler\OpenApi\Validator\Error\ValidationContext;

use function sprintf;

#[CoversClass(UnevaluatedItemsValidator::class)]
class UnevaluatedItemsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private UnevaluatedItemsValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new UnevaluatedItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
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

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, 'ab', 'cd'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, 'extra', 'items'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate('string value', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_unevaluated_items_is_null(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, 'extra'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['a', 'b', 'c'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate([], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['hello'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_items_without_prefix_items_or_items(): void
    {
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: $unevaluatedSchema,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['a', 'b', 'c'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, 43], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['a', 'b', 'c'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * C-002 regression: prefixItems + items together. Items schema
     * evaluates every index >= prefixItems count, so the unevaluated
     * set must be empty for data whose tail items all conform to items.
     */
    #[Test]
    public function unevaluated_items_with_prefix_items_and_items_schema_passes_tail(): void
    {
        $prefix = new Schema(type: 'string');
        $items = new Schema(type: 'integer');
        $unevaluated = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefix],
            items: $items,
            unevaluatedItems: $unevaluated,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['foo', 1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * C-003 regression: contains annotations register matched indices,
     * and unevaluatedItems reads them through ValidationContext.
     * Indices 0,1 are marked evaluated via annotations; index 2 ("x")
     * remains unevaluated and fails the boolean unevaluatedItems schema.
     */
    #[Test]
    public function unevaluated_items_with_contains_only_fails_unevaluated_index(): void
    {
        $contains = new Schema(type: 'integer', minimum: 0);
        $unevaluated = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            contains: $contains,
            unevaluatedItems: $unevaluated,
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $context->markItemEvaluated(0);
        $context->markItemEvaluated(1);

        $caught = null;

        try {
            $this->validator->validate([1, 2, 'x'], $schema, $context);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Index 2 ("x") is unevaluated and fails the boolean unevaluatedItems schema');
    }

    /**
     * C-003 anti-test seed: without annotations, all three indices are
     * considered unevaluated; even valid items at 0,1 fail the boolean
     * unevaluatedItems schema.
     */
    #[Test]
    public function unevaluated_items_without_contains_annotations_fails_on_all_indices(): void
    {
        $contains = new Schema(type: 'integer', minimum: 0);
        $unevaluated = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            contains: $contains,
            unevaluatedItems: $unevaluated,
        );

        $caught = null;

        try {
            $this->validator->validate([1, 2, 'x'], $schema);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Without annotations, integer items 1,2 fail the boolean unevaluatedItems schema');
    }

    /**
     * C-004 regression: composition-validated items contribute
     * evaluated indices via context annotations.
     */
    #[Test]
    public function unevaluated_items_uses_composition_annotations_from_context(): void
    {
        $unevaluated = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: $unevaluated,
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $context->markItemEvaluated(0);
        $context->markItemEvaluated(1);
        $context->markItemEvaluated(2);

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass when all indices are evaluated via annotations, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_items_without_context_falls_back_to_static_analysis(): void
    {
        $unevaluated = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: $unevaluated,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['a', 'b', 'c'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass for string items against string unevaluated schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_items_with_prefix_items_only_evaluates_prefix_indices(): void
    {
        $prefix1 = new Schema(type: 'string');
        $prefix2 = new Schema(type: 'integer');
        $unevaluated = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefix1, $prefix2],
            unevaluatedItems: $unevaluated,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, 'tail1', 'tail2'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass with prefixItems[0,1] and tail items passing unevaluated string schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_items_out_of_range_annotations_are_ignored(): void
    {
        $unevaluated = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: $unevaluated,
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $context->markItemEvaluated(0);
        $context->markItemEvaluated(99);

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Out-of-range annotation 99 must be ignored; tail indices 1,2 pass integer unevaluated schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
