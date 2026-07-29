<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function sprintf;

#[CoversClass(PrefixItemsValidator::class)]
class PrefixItemsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PrefixItemsValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PrefixItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function validate_tuple_items(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2, $schema3],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, true], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_additional_items_with_items_schema(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $additionalSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $prefixSchema2],
            items: $additionalSchema,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, 3.14, 2.71], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_tuple_item(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
        );

        $caught = null;

        try {
            $this->validator->validate(['hello', 'world'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
    }

    #[Test]
    public function prefixItems_does_not_validate_remaining_items(): void
    {
        $prefixSchema = new Schema(type: 'string');
        $itemsSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
            items: $itemsSchema,
        );

        $this->validator->validate(['hello', 'world', 'extra'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_fewer_items_than_prefix_items(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2, $schema3],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
        );

        $succeeded = false;

        try {
            $this->validator->validate('string value', $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_validation_for_associative_array(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['key' => 'value'], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_prefix_items_is_null(): void
    {
        $schema = new Schema(type: 'array');

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_prefix_items_is_empty(): void
    {
        $schema = new Schema(type: 'array', prefixItems: []);

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_empty_array(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
        );

        $succeeded = false;

        try {
            $this->validator->validate([], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function allow_additional_items_when_no_items_schema(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42, true, 'extra'], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_prefix_items_with_middle_schema_failing(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2, $schema3],
        );

        $caught = null;

        try {
            $this->validator->validate(['hello', 'not integer', true], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
    }

    #[Test]
    public function validate_prefix_items_with_last_schema_failing(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2, $schema3],
        );

        $caught = null;

        try {
            $this->validator->validate(['hello', 42, 'not boolean'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
    }

    #[Test]
    public function validate_prefix_items_throws_exception_for_invalid_item(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['hello', new stdClass()], $schema);
    }

    #[Test]
    public function validate_prefix_items_exceeds_count_with_items_schema(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema3 = new Schema(type: 'boolean');
        $itemsSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
            items: $itemsSchema,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['a', 1, 'extra1', 'extra2'], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_prefix_items_with_nullable_prefix_item(): void
    {
        $schema1 = new Schema(type: 'string', nullable: true);
        $schema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $succeeded = false;

        try {
            $this->validator->validate([null, 42], $schema, $context);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_prefix_items_with_nullable_items(): void
    {
        $schema1 = new Schema(type: 'string');
        $itemsSchema = new Schema(type: 'string', nullable: true);
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1],
            items: $itemsSchema,
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $succeeded = false;

        try {
            $this->validator->validate(['hello', null, 'world'], $schema, $context);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_prefix_items_with_context(): void
    {
        $schema1 = new Schema(type: 'string');
        $schema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $succeeded = false;

        try {
            $this->validator->validate(['hello', 42], $schema, $context);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_prefix_items_nested_schemas(): void
    {
        $nestedSchema = new Schema(type: 'object', properties: ['value' => new Schema(type: 'string')]);
        $schema1 = new Schema(type: 'string');
        $schema2 = $nestedSchema;
        $schema = new Schema(
            type: 'array',
            prefixItems: [$schema1, $schema2],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['hello', ['value' => 'test']], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_validation_exception_for_prefix_item_validation_failed(): void
    {
        $prefixSchema1 = new Schema(
            not: new Schema(type: 'string'),
        );
        $schema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema1, $schema2],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Item at index 0 validation failed');

        $this->validator->validate(['string_value', 42], $schema);
    }

    #[Test]
    public function rethrow_invalid_format_exception_from_prefix_item_without_wrapping(): void
    {
        $prefixSchema = new Schema(type: 'string', format: 'email');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
        );

        $this->expectException(InvalidFormatException::class);

        $this->validator->validate(['not-an-email'], $schema);
    }

    #[Test]
    public function validate_prefix_item_creates_context_when_none_supplied(): void
    {
        $prefixSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
        );

        $this->validator->validate(['hello'], $schema, null);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_prefix_item_with_composition_one_of_branch_resolves_correctly(): void
    {
        $prefixSchema = new Schema(
            oneOf: [
                new Schema(type: 'integer'),
                new Schema(type: 'string'),
            ],
        );
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);
        $succeeded = false;

        try {
            $this->validator->validate([42], $schema, $context);
            $this->validator->validate(['hello'], $schema, $context);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue($succeeded);
    }

    #[Test]
    public function validate_prefix_item_with_nullable_type_union_accepts_null_at_position(): void
    {
        $prefixSchema = new Schema(type: ['string', 'null']);
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema, new Schema(type: 'integer')],
        );

        $context = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validate([null, 42], $schema, $context);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_prefix_item_min_items_above_prefix_count_validates_only_available_positions(): void
    {
        $prefixSchema1 = new Schema(type: 'string');
        $prefixSchema2 = new Schema(type: 'integer');
        $prefixSchema3 = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            minItems: 5,
            prefixItems: [$prefixSchema1, $prefixSchema2, $prefixSchema3],
        );

        $this->validator->validate(['hello', 42, true], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_prefix_item_without_trailing_items_keyword_allows_extra_untyped_items(): void
    {
        $prefixSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
        );

        $this->validator->validate(['hello', 42, true, ['nested'], null], $schema);

        $this->expectNotToPerformAssertions();
    }
}
