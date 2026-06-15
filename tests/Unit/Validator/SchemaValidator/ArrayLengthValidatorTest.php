<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(ArrayLengthValidator::class)]
class ArrayLengthValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ArrayLengthValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ArrayLengthValidator($this->pool, BuiltinFormats::create());
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

        $this->expectException(DuplicateItemsError::class);

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

        $this->expectException(DuplicateItemsError::class);

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

    #[Test]
    public function unique_items_detects_duplicate_associative_arrays_via_deep_equality(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $caught = null;

        try {
            $this->validator->validate([['a' => 1], ['a' => 1]], $schema);
        } catch (DuplicateItemsError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('uniqueItems', $caught->keyword());
        self::assertSame('/uniqueItems', $caught->schemaPath());
        self::assertSame(2, $caught->params()['expected']);
        self::assertSame(1, $caught->params()['actual']);
    }

    #[Test]
    public function unique_items_accepts_distinct_associative_arrays(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $succeeded = false;

        try {
            $this->validator->validate([['a' => 1], ['a' => 2]], $schema);
            $succeeded = true;
        } catch (DuplicateItemsError $e) {
            self::fail(sprintf('Expected distinct objects to pass uniqueItems, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unique_items_detects_duplicate_nested_sequential_arrays(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $caught = null;

        try {
            $this->validator->validate([[1, 2], [1, 2]], $schema);
        } catch (DuplicateItemsError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('uniqueItems', $caught->keyword());
        self::assertSame('/uniqueItems', $caught->schemaPath());
    }

    #[Test]
    public function unique_items_accepts_distinct_nested_sequential_arrays(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $succeeded = false;

        try {
            $this->validator->validate([[1, 2], [3, 4]], $schema);
            $succeeded = true;
        } catch (DuplicateItemsError $e) {
            self::fail(sprintf('Expected distinct nested arrays to pass uniqueItems, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unique_items_treats_distinct_scalar_json_types_as_unique(): void
    {
        // JSON Schema draft 2020-12 §6.4.3: items of different JSON types
        // (number, string, boolean) are never equal. Previously this array
        // was wrongly rejected because PHP loose comparison (SORT_REGULAR)
        // collapsed 1 == "1" == true into duplicates.
        $schema = new Schema(type: 'array', uniqueItems: true);

        $succeeded = false;

        try {
            $this->validator->validate([1, '1', true], $schema);
            $succeeded = true;
        } catch (DuplicateItemsError $e) {
            self::fail(sprintf(
                'Expected [1, "1", true] to pass uniqueItems (distinct JSON types), got: %s',
                $e->getMessage(),
            ));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * Regression for review finding on bugfix 18: int and float that are
     * mathematically equal MUST be treated as duplicates per JSON Schema
     * §4.2.3 (numeric equality). PHP's strict === returns false for
     * 1 === 1.0 because the types differ, which previously caused
     * uniqueItems to silently accept [1, 1.0] as having no duplicates.
     */
    #[Test]
    public function unique_items_detects_int_and_float_as_duplicate_when_mathematically_equal(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $caught = null;

        try {
            $this->validator->validate([1, 1.0], $schema);
        } catch (DuplicateItemsError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Expected DuplicateItemsError for [1, 1.0] (numeric equality per JSON Schema §4.2.3).');
        self::assertSame('uniqueItems', $caught->keyword());
        self::assertSame('/uniqueItems', $caught->schemaPath());
    }

    #[Test]
    public function unique_items_accepts_distinct_mixed_scalar_types(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $succeeded = false;

        try {
            $this->validator->validate([42, 3.14, 'hello'], $schema);
            $succeeded = true;
        } catch (DuplicateItemsError $e) {
            self::fail(sprintf('Expected distinct mixed scalar types to pass uniqueItems, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unique_items_accepts_distinct_scalars_and_arrays_and_objects(): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $succeeded = false;

        try {
            $this->validator->validate([1, ['nested' => 'array'], ['key' => 'value']], $schema);
            $succeeded = true;
        } catch (DuplicateItemsError $e) {
            self::fail(sprintf('Expected distinct values of different shapes to pass uniqueItems, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * SV-06 (uniqueItems) parameterized cases covering distinct arrays that
     * MUST pass validation. Complements the individual uniqueItems tests
     * above (which carry specific assertions about error metadata and JSON
     * type-aware equality semantics) with a single data-provider-driven sweep.
     *
     * @return array<string, array{0: array<mixed>}>
     */
    public static function provideUniqueItemsCases(): array
    {
        return [
            'distinct_integers' => [[1, 2, 3, 4]],
            'distinct_strings' => [['a', 'b', 'c']],
            'empty_array' => [[]],
            'single_element' => [[42]],
            'distinct_mixed_scalars' => [[42, 3.14, 'hello']],
            'distinct_mixed_json_types' => [[1, '1', true]],
            'distinct_associative_arrays' => [[['a' => 1], ['a' => 2]]],
            'distinct_nested_sequential_arrays' => [[1, ['nested' => 'array'], ['key' => 'value']]],
        ];
    }

    #[Test]
    #[DataProvider('provideUniqueItemsCases')]
    public function sv_06_unique_items_accepts_distinct_arrays(array $data): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $succeeded = false;

        try {
            $this->validator->validate($data, $schema);
            $succeeded = true;
        } catch (DuplicateItemsError $e) {
            self::fail(sprintf('Expected distinct array to pass uniqueItems, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * SV-06 (uniqueItems) parameterized cases covering duplicate arrays that
     * MUST fail validation with DuplicateItemsError.
     *
     * @return array<string, array{0: array<mixed>}>
     */
    public static function provideDuplicateItemsCases(): array
    {
        return [
            'duplicate_integers' => [[1, 2, 2, 3]],
            'duplicate_strings' => [['a', 'b', 'a']],
            'duplicate_associative_arrays' => [[['a' => 1], ['a' => 1]]],
            'duplicate_nested_sequential_arrays' => [[[1, 2], [1, 2]]],
        ];
    }

    #[Test]
    #[DataProvider('provideDuplicateItemsCases')]
    public function sv_06_unique_items_rejects_duplicate_arrays(array $data): void
    {
        $schema = new Schema(type: 'array', uniqueItems: true);

        $caught = null;

        try {
            $this->validator->validate($data, $schema);
        } catch (DuplicateItemsError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Expected DuplicateItemsError for duplicate array.');
        self::assertSame('uniqueItems', $caught->keyword());
        self::assertSame('/uniqueItems', $caught->schemaPath());
    }
}
