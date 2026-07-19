<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use stdClass;

use const JSON_THROW_ON_ERROR;
use const PHP_INT_MAX;

#[CoversClass(JsonEquals::class)]
final class JsonEqualsTest extends TestCase
{
    // ---- Original tests retained / adjusted ----

    #[Test]
    public function equals_int_and_float_with_same_math_value(): void
    {
        self::assertTrue(JsonEquals::equals(1, 1.0));
    }

    #[Test]
    public function equals_float_and_int_is_commutative(): void
    {
        self::assertTrue(JsonEquals::equals(1.0, 1));
    }

    #[Test]
    public function equals_zero_int_and_zero_float(): void
    {
        self::assertTrue(JsonEquals::equals(0, 0.0));
    }

    #[Test]
    public function not_equals_when_numeric_values_differ(): void
    {
        self::assertFalse(JsonEquals::equals(1, 2));
    }

    #[Test]
    public function not_equals_int_and_string_with_same_text(): void
    {
        self::assertFalse(JsonEquals::equals(1, '1'));
    }

    #[Test]
    public function not_equals_number_and_boolean(): void
    {
        self::assertFalse(JsonEquals::equals(1, true));
    }

    #[Test]
    public function equals_strings_with_same_value(): void
    {
        self::assertTrue(JsonEquals::equals('a', 'a'));
    }

    #[Test]
    public function equals_booleans_with_same_value(): void
    {
        self::assertTrue(JsonEquals::equals(true, true));
    }

    #[Test]
    public function equals_null_values(): void
    {
        self::assertTrue(JsonEquals::equals(null, null));
    }

    #[Test]
    public function not_equals_null_and_false(): void
    {
        self::assertFalse(JsonEquals::equals(null, false));
    }

    #[Test]
    public function equals_empty_arrays(): void
    {
        self::assertTrue(JsonEquals::equals([], []));
    }

    #[Test]
    public function equals_identical_arrays(): void
    {
        self::assertTrue(JsonEquals::equals([1, 2, 3], [1, 2, 3]));
    }

    /**
     * JSON Schema 2020-12 §4.2.2: int 1 and float 1.0 are mathematically
     * equal, so lists that differ only in scalar numeric representation
     * MUST be equal. The previous implementation returned false here because
     * PHP's `===` treats 1 and 1.0 as distinct types.
     */
    #[Test]
    public function equals_arrays_with_equivalent_numeric_representation(): void
    {
        self::assertTrue(JsonEquals::equals([1], [1.0]));
    }

    // ---- SPEC-05: int64 precision ----

    /**
     * SPEC-05: casting distinct int64 values to float collapses them —
     * 9223372036854775806 and 9223372036854775807 both round to
     * 9.223372036854776E+18. JSON Schema treats them as distinct numbers.
     */
    #[Test]
    public function int64_equality_preserves_precision(): void
    {
        self::assertFalse(JsonEquals::equals(9223372036854775806, 9223372036854775807));
    }

    #[Test]
    public function int64_equal_values_match(): void
    {
        self::assertTrue(JsonEquals::equals(PHP_INT_MAX, PHP_INT_MAX));
    }

    #[Test]
    public function int64_neg_max_distinct_from_pos_max(): void
    {
        self::assertFalse(JsonEquals::equals(-9223372036854775807 - 1, PHP_INT_MAX));
    }

    // ---- SPEC-04: bool vs int ----

    #[Test]
    public function bool_true_not_equal_int_1(): void
    {
        self::assertFalse(JsonEquals::equals(true, 1));
    }

    #[Test]
    public function bool_false_not_equal_int_0(): void
    {
        self::assertFalse(JsonEquals::equals(false, 0));
    }

    #[Test]
    public function bool_true_equal_true(): void
    {
        self::assertTrue(JsonEquals::equals(true, true));
    }

    #[Test]
    public function bool_false_equal_false(): void
    {
        self::assertTrue(JsonEquals::equals(false, false));
    }

    #[Test]
    public function bool_distinct_values_not_equal(): void
    {
        self::assertFalse(JsonEquals::equals(true, false));
    }

    // ---- SPEC-03: object order independence ----

    #[Test]
    public function object_order_independent(): void
    {
        self::assertTrue(JsonEquals::equals(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]));
    }

    #[Test]
    public function array_order_sensitive(): void
    {
        self::assertFalse(JsonEquals::equals([1, 2, 3], [3, 2, 1]));
    }

    #[Test]
    public function string_not_equal_int(): void
    {
        self::assertFalse(JsonEquals::equals('1', 1));
    }

    #[Test]
    public function null_not_equal_int_zero(): void
    {
        self::assertFalse(JsonEquals::equals(null, 0));
    }

    #[Test]
    public function nested_object_order_independent(): void
    {
        $a = ['outer' => ['a' => 1, 'b' => 2]];
        $b = ['outer' => ['b' => 2, 'a' => 1]];

        self::assertTrue(JsonEquals::equals($a, $b));
    }

    #[Test]
    public function nested_object_with_array_value_order_independent(): void
    {
        $a = ['outer' => ['a' => 1, 'b' => 2], 'list' => [1, 2, 3]];
        $b = ['list' => [1, 2, 3], 'outer' => ['b' => 2, 'a' => 1]];

        self::assertTrue(JsonEquals::equals($a, $b));
    }

    #[Test]
    public function nested_list_order_remains_significant(): void
    {
        self::assertFalse(
            JsonEquals::equals(['list' => [1, 2, 3]], ['list' => [3, 2, 1]]),
        );
    }

    #[Test]
    public function object_with_extra_key_not_equal(): void
    {
        self::assertFalse(JsonEquals::equals(['a' => 1], ['a' => 1, 'b' => 2]));
    }

    #[Test]
    public function object_with_different_value_not_equal(): void
    {
        self::assertFalse(JsonEquals::equals(['a' => 1], ['a' => 2]));
    }

    #[Test]
    public function list_with_different_length_not_equal(): void
    {
        self::assertFalse(JsonEquals::equals([1, 2], [1, 2, 3]));
    }

    // ---- Integration: uniqueItems / const / enum ----

    /**
     * SPEC-05 integration: `[PHP_INT_MAX - 1, PHP_INT_MAX]` MUST NOT trigger
     * a false-positive DuplicateItemsError under `uniqueItems: true`.
     */
    #[Test]
    public function uniqueItems_int64_no_false_duplicate(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithUniqueItemsArray())
            ->build();

        $validator->validateSchema(
            [9223372036854775806, 9223372036854775807],
            '#/components/schemas/Bag',
        );

        $this->expectNotToPerformAssertions();
    }

    /**
     * SPEC-03 integration: `const: {b: 2, a: 1}` MUST accept `{a: 1, b: 2}`
     * (object key order independence).
     */
    #[Test]
    public function const_object_order_independent(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithConst(['b' => 2, 'a' => 1]))
            ->build();

        $validator->validateSchema(['a' => 1, 'b' => 2], '#/components/schemas/Const');

        $this->expectNotToPerformAssertions();
    }

    /**
     * SPEC-03 integration: `enum: [{b: 2, a: 1}]` MUST accept `{a: 1, b: 2}`.
     */
    #[Test]
    public function enum_object_order_independent(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithEnum([['b' => 2, 'a' => 1]]))
            ->build();

        $validator->validateSchema(['a' => 1, 'b' => 2], '#/components/schemas/Enum');

        $this->expectNotToPerformAssertions();
    }

    /**
     * SPEC-04 integration: `const: true` MUST reject int 1.
     */
    #[Test]
    public function const_bool_true_rejects_int_one(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithConst(true))
            ->build();

        $this->expectException(ValidationException::class);

        try {
            $validator->validateSchema(1, '#/components/schemas/Const');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            self::assertNotEmpty($errors);
            self::assertInstanceOf(ConstError::class, $errors[0]);

            throw $e;
        }
    }

    /**
     * SPEC-05 integration: `enum: [PHP_INT_MAX - 1, PHP_INT_MAX]` MUST accept
     * `PHP_INT_MAX` and MUST reject `PHP_INT_MAX - 2`.
     */
    #[Test]
    public function enum_int64_lookup_matches_correct_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithEnum([9223372036854775806, 9223372036854775807]))
            ->build();

        // Positive match — exact int64 value present in enum.
        $validator->validateSchema(9223372036854775807, '#/components/schemas/Enum');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function enum_int64_rejects_close_but_distinct_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($this->specWithEnum([9223372036854775806]))
            ->build();

        $caught = null;

        try {
            $validator->validateSchema(9223372036854775807, '#/components/schemas/Enum');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        $errors = $caught->getErrors();
        self::assertNotEmpty($errors);
        self::assertInstanceOf(EnumError::class, $errors[0]);
    }

    private function specWithUniqueItemsArray(): string
    {
        return <<<'JSON'
            {
                "openapi": "3.2.0",
                "info": {"title": "test", "version": "1.0.0"},
                "paths": {},
                "components": {
                    "schemas": {
                        "Bag": {
                            "type": "array",
                            "uniqueItems": true
                        }
                    }
                }
            }
            JSON;
    }

    private function specWithConst(mixed $value): string
    {
        $encoded = json_encode(['openapi' => '3.2.0', 'info' => ['title' => 'test', 'version' => '1.0.0'], 'paths' => new stdClass(), 'components' => ['schemas' => ['Const' => ['const' => $value]]]], JSON_THROW_ON_ERROR);

        return $encoded;
    }

    private function specWithEnum(array $values): string
    {
        return json_encode([
            'openapi' => '3.2.0',
            'info' => ['title' => 'test', 'version' => '1.0.0'],
            'paths' => new stdClass(),
            'components' => ['schemas' => ['Enum' => ['enum' => $values]]],
        ], JSON_THROW_ON_ERROR);
    }
}
