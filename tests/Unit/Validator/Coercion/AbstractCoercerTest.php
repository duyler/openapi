<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Coercion;

use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const INF;
use const NAN;

final class AbstractCoercerTest extends TestCase
{
    private ConcreteCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new ConcreteCoercer();
    }

    #[Test]
    public function is_valid_type_returns_true_for_string(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType('hello', 'string'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_non_string(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType(123, 'string'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_number_float(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(3.14, 'number'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_number_int(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(42, 'number'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_non_number(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType('42', 'number'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_integer(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(42, 'integer'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_float_as_integer(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType(3.14, 'integer'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_boolean(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(true, 'boolean'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_null(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(null, 'null'));
    }

    #[Test]
    public function is_valid_type_returns_false_for_non_null(): void
    {
        $this->assertFalse($this->coercer->exposedIsValidType('null', 'null'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_object_array(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType(['key' => 'value'], 'object'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_array(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType([1, 2, 3], 'array'));
    }

    #[Test]
    public function is_valid_type_returns_true_for_unknown_type(): void
    {
        $this->assertTrue($this->coercer->exposedIsValidType('anything', 'custom'));
    }

    #[Test]
    public function coerce_to_boolean_returns_bool_unchanged(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean(true));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(false));
    }

    #[Test]
    public function coerce_to_boolean_converts_truthy_strings(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('true'));
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('1'));
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('yes'));
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('on'));
    }

    #[Test]
    public function coerce_to_boolean_converts_falsy_strings(): void
    {
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('false'));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('0'));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('no'));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean('off'));
    }

    #[Test]
    public function coerce_to_boolean_converts_int(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean(1));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(0));
    }

    #[Test]
    public function coerce_to_boolean_converts_float(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean(1.5));
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(0.0));
    }

    #[Test]
    public function coerce_to_boolean_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToBoolean([]));
    }

    #[Test]
    public function coerce_to_boolean_strict_throws_on_invalid_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToBooleanStrict('invalid');
    }

    #[Test]
    public function coerce_to_boolean_strict_throws_on_admin_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToBooleanStrict('admin');
    }

    #[Test]
    public function coerce_to_boolean_strict_accepts_true_string(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBooleanStrict('true'));
    }

    #[Test]
    public function coerce_to_boolean_strict_accepts_false_string(): void
    {
        $this->assertFalse($this->coercer->exposedCoerceToBooleanStrict('false'));
    }

    #[Test]
    public function coerce_to_boolean_strict_throws_on_empty_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToBooleanStrict('');
    }

    #[Test]
    public function coerce_to_boolean_strict_accepts_uppercase_true(): void
    {
        $result = $this->coercer->exposedCoerceToBooleanStrict('TRUE');

        $this->assertSame(true, $result);
    }

    #[Test]
    public function coerce_to_boolean_non_strict_returns_true_for_invalid_string(): void
    {
        $this->assertTrue($this->coercer->exposedCoerceToBoolean('invalid'));
    }

    #[Test]
    public function coerce_to_boolean_non_strict_returns_false_for_empty_string(): void
    {
        $this->assertFalse($this->coercer->exposedCoerceToBoolean(''));
    }

    #[Test]
    public function coerce_to_integer_returns_int_unchanged(): void
    {
        $this->assertSame(42, $this->coercer->exposedCoerceToInteger(42));
    }

    #[Test]
    public function coerce_to_integer_converts_numeric_string(): void
    {
        $this->assertSame(42, $this->coercer->exposedCoerceToInteger('42'));
    }

    #[Test]
    public function coerce_to_integer_converts_negative_signed_string(): void
    {
        $this->assertSame(-5, $this->coercer->exposedCoerceToInteger('-5'));
    }

    #[Test]
    public function coerce_to_integer_converts_positive_signed_string(): void
    {
        $this->assertSame(5, $this->coercer->exposedCoerceToInteger('+5'));
    }

    #[Test]
    public function coerce_to_integer_accepts_leading_zero_string(): void
    {
        $this->assertSame(8, $this->coercer->exposedCoerceToInteger('08'));
    }

    #[Test]
    public function coerce_to_integer_throws_on_strict_non_numeric_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('abc');
    }

    #[Test]
    public function coerce_to_integer_throws_on_strict_float_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('3.14');
    }

    #[Test]
    public function coerce_to_integer_scientific_notation_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToInteger('1e5');

        $this->assertSame('1e5', $result);
    }

    #[Test]
    public function coerce_to_integer_scientific_notation_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('1e5');
    }

    #[Test]
    public function coerce_to_integer_trailing_chars_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToInteger('12abc');

        $this->assertSame('12abc', $result);
    }

    #[Test]
    public function coerce_to_integer_trailing_chars_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('12abc');
    }

    #[Test]
    public function coerce_to_integer_hex_notation_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToInteger('0x10');

        $this->assertSame('0x10', $result);
    }

    #[Test]
    public function coerce_to_integer_hex_notation_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('0x10');
    }

    #[Test]
    public function coerce_to_integer_empty_string_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToInteger('');

        $this->assertSame('', $result);
    }

    #[Test]
    public function coerce_to_integer_empty_string_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('');
    }

    #[Test]
    public function coerce_to_integer_whole_float_non_strict_returns_int(): void
    {
        $result = $this->coercer->exposedCoerceToInteger(3.0);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function coerce_to_integer_fractional_float_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToInteger(3.14);

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_to_integer_throws_on_strict_float(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict(3.14);
    }

    #[Test]
    public function coerce_to_integer_converts_bool(): void
    {
        $this->assertSame(1, $this->coercer->exposedCoerceToInteger(true));
        $this->assertSame(0, $this->coercer->exposedCoerceToInteger(false));
    }

    #[Test]
    public function coerce_to_integer_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToInteger([]));
    }

    #[Test]
    public function coerce_to_integer_overflow_non_strict_returns_string_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToInteger('99999999999999999999');

        $this->assertSame('99999999999999999999', $result);
    }

    #[Test]
    public function coerce_to_integer_overflow_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToIntegerStrict('99999999999999999999');
    }

    #[Test]
    public function coerce_to_number_returns_float_unchanged(): void
    {
        $this->assertSame(3.14, $this->coercer->exposedCoerceToNumber(3.14));
    }

    #[Test]
    public function coerce_to_number_converts_int_to_float(): void
    {
        $this->assertSame(42.0, $this->coercer->exposedCoerceToNumber(42));
    }

    #[Test]
    public function coerce_to_number_converts_string(): void
    {
        $this->assertSame(3.14, $this->coercer->exposedCoerceToNumber('3.14'));
    }

    #[Test]
    public function coerce_to_number_converts_scientific_notation_string(): void
    {
        $this->assertSame(100000.0, $this->coercer->exposedCoerceToNumber('1e5'));
        $this->assertSame(15000000000.0, $this->coercer->exposedCoerceToNumber('1.5e10'));
        $this->assertSame(-0.00023, $this->coercer->exposedCoerceToNumber('-2.3E-4'));
    }

    #[Test]
    public function coerce_to_number_throws_on_strict_non_numeric(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToNumberStrict('abc');
    }

    #[Test]
    public function coerce_to_number_trailing_chars_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('12abc');

        $this->assertSame('12abc', $result);
    }

    #[Test]
    public function coerce_to_number_trailing_chars_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToNumberStrict('12abc');
    }

    #[Test]
    public function coerce_to_number_hex_notation_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('0x10');

        $this->assertSame('0x10', $result);
    }

    #[Test]
    public function coerce_to_number_hex_notation_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToNumberStrict('0x10');
    }

    #[Test]
    public function coerce_to_number_empty_string_non_strict_returns_as_is(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('');

        $this->assertSame('', $result);
    }

    #[Test]
    public function coerce_to_number_empty_string_strict_throws(): void
    {
        $this->expectException(TypeMismatchError::class);

        $this->coercer->exposedCoerceToNumberStrict('');
    }

    #[Test]
    public function coerce_to_number_converts_bool(): void
    {
        $this->assertSame(1.0, $this->coercer->exposedCoerceToNumber(true));
        $this->assertSame(0.0, $this->coercer->exposedCoerceToNumber(false));
    }

    #[Test]
    public function coerce_to_number_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToNumber([]));
    }

    #[Test]
    public function coerce_to_string_returns_string_unchanged(): void
    {
        $this->assertSame('hello', $this->coercer->exposedCoerceToString('hello'));
    }

    #[Test]
    public function coerce_to_string_converts_int(): void
    {
        $this->assertSame('42', $this->coercer->exposedCoerceToString(42));
    }

    #[Test]
    public function coerce_to_string_converts_float(): void
    {
        $this->assertSame('3.14', $this->coercer->exposedCoerceToString(3.14));
    }

    #[Test]
    public function coerce_to_string_converts_bool(): void
    {
        $this->assertSame('1', $this->coercer->exposedCoerceToString(true));
        $this->assertSame('', $this->coercer->exposedCoerceToString(false));
    }

    #[Test]
    public function coerce_to_string_returns_unknown_as_is(): void
    {
        $this->assertSame([], $this->coercer->exposedCoerceToString([]));
    }

    /**
     * Anti-test: to verify this test guards against regression, temporarily
     * remove the overflow check ($value > PHP_INT_MAX || $value < PHP_INT_MIN)
     * in AbstractCoercer::coerceToInteger(). Without the guard, (int) 1e20
     * triggers a PHP deprecation notice ("not representable as int") and
     * returns platform-dependent undefined behavior (commonly 0 or
     * 7766279631452241920) instead of throwing TypeMismatchError. The same
     * regression disables coerce_to_integer_throws_on_float_overflow_below_int_min.
     */
    #[Test]
    public function coerce_to_integer_throws_on_float_overflow_above_int_max(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Float value out of integer range');

        $this->coercer->exposedCoerceToInteger(1.0E+20);
    }

    #[Test]
    public function coerce_to_integer_throws_on_float_overflow_below_int_min(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Float value out of integer range');

        $this->coercer->exposedCoerceToInteger(-1.0E+20);
    }

    /**
     * Anti-test: to verify this test guards against regression, temporarily
     * restore the previous guard ($value > PHP_INT_MAX || $value < PHP_INT_MIN)
     * in AbstractCoercer::coerceToInteger(). The previous float-vs-int
     * comparison missed the bypass double (float) PHP_INT_MAX =
     * 9223372036854775808.0 (= PHP_INT_MAX + 1) because PHP implicitly
     * coerces PHP_INT_MAX to the same float, leaving `$value > PHP_INT_MAX`
     * false at the boundary. The subsequent (int) cast then wrapped around
     * to a non-int64 value with a PHP 8.4 deprecation warning (PHP 9.0
     * promotes this to TypeError). The same regression disables
     * coerce_to_integer_rejects_float_just_below_php_int_max and
     * coerce_to_integer_rejects_float_just_above_php_int_min.
     */
    #[Test]
    public function coerce_to_integer_rejects_float_at_php_int_max_boundary(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Float value out of integer range');

        $this->coercer->exposedCoerceToInteger((float) PHP_INT_MAX);
    }

    #[Test]
    public function coerce_to_integer_rejects_float_just_below_php_int_max(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Float value out of integer range');

        $this->coercer->exposedCoerceToInteger((float) (PHP_INT_MAX - 1));
    }

    #[Test]
    public function coerce_to_integer_rejects_float_just_above_php_int_min(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Float value out of integer range');

        $this->coercer->exposedCoerceToInteger((float) (PHP_INT_MIN + 1));
    }

    #[Test]
    public function coerce_to_integer_accepts_whole_float_in_safe_range(): void
    {
        $result = $this->coercer->exposedCoerceToInteger(42.0);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function coerce_to_integer_accepts_large_whole_float_within_range(): void
    {
        // 2^50 = 1125899906842624 is exactly representable as a double and
        // well within int64 range. PHP_INT_MAX itself rounds to PHP_INT_MAX+1
        // when cast to float, so the int boundary is not exactly testable
        // through float.
        $value = (float) (2 ** 50);

        $result = $this->coercer->exposedCoerceToInteger($value);

        $this->assertSame(2 ** 50, $result);
    }

    #[Test]
    public function coerce_to_integer_accepts_negative_whole_float_within_range(): void
    {
        $value = (float) -(2 ** 50);

        $result = $this->coercer->exposedCoerceToInteger($value);

        $this->assertSame(-(2 ** 50), $result);
    }

    #[Test]
    public function coerce_to_integer_accepts_small_whole_float(): void
    {
        $result = $this->coercer->exposedCoerceToInteger(42.0);

        $this->assertSame(42, $result);
    }

    /**
     * Anti-test for R4-SPEC-005: to verify this test guards against regression,
     * temporarily restore the unconditional `is_float($value)` rejection in
     * AbstractCoercer::coerceToIntegerStrict(). Without the JSON Schema 2020-12
     * §4.2.3 fix, a whole-valued float like 3.0 is rejected even though the
     * runtime TypeValidator accepts it (asymmetric behavior between coercion
     * enabled and coercion disabled).
     */
    #[Test]
    public function coerce_to_integer_strict_accepts_whole_float_3_0(): void
    {
        $result = $this->coercer->exposedCoerceToIntegerStrict(3.0);

        $this->assertSame(3, $result);
    }

    #[Test]
    public function coerce_to_integer_strict_accepts_negative_whole_float(): void
    {
        $result = $this->coercer->exposedCoerceToIntegerStrict(-7.0);

        $this->assertSame(-7, $result);
    }

    #[Test]
    public function coerce_to_integer_strict_accepts_zero_whole_float(): void
    {
        $result = $this->coercer->exposedCoerceToIntegerStrict(0.0);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function coerce_to_integer_strict_rejects_fractional_float_with_reason(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict(3.14);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame('Float value has non-zero fractional part', $e->reason());
        }
    }

    #[Test]
    public function coerce_to_integer_strict_rejects_inf_with_reason(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict(INF);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame('Cannot coerce INF to integer', $e->reason());
        }
    }

    #[Test]
    public function coerce_to_integer_strict_rejects_negative_inf_with_reason(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict(-INF);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame('Cannot coerce INF to integer', $e->reason());
        }
    }

    #[Test]
    public function coerce_to_integer_strict_rejects_nan_with_reason(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict(NAN);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame('Cannot coerce NaN to integer', $e->reason());
        }
    }

    /**
     * Anti-test for R4-SPEC-005 safe-integer-range guard: 2^53 + 1
     * (9007199254740993.0) is silently rounded to 2^53 by IEEE-754 double
     * precision, so fmod($value, 1.0) === 0.0 even though the value is no
     * longer exactly representable. Without the |value| < 2^53 guard, the
     * strict coercion would silently truncate 9007199254740993.0 to
     * 9007199254740992 with no diagnostic.
     */
    #[Test]
    public function coerce_to_integer_strict_rejects_unsafe_whole_float_above_2_pow_53(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict(9007199254740993.0);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame(
                'Float value exceeds safe integer range (|value| >= 2^53)',
                $e->reason(),
            );
        }
    }

    #[Test]
    public function coerce_to_integer_strict_accepts_safe_boundary_2_pow_53_minus_1(): void
    {
        $result = $this->coercer->exposedCoerceToIntegerStrict(9007199254740991.0);

        $this->assertSame(9007199254740991, $result);
    }

    #[Test]
    public function coerce_to_integer_strict_rejects_negative_unsafe_whole_float(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict(-9007199254740993.0);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame(
                'Float value exceeds safe integer range (|value| >= 2^53)',
                $e->reason(),
            );
        }
    }

    /**
     * Anti-test for R4-SEC-013 (strict variant): without the floatExceedsInt64Range
     * guard in coerceToIntegerStrict, a whole-valued float that lands below the
     * 2^53 safe-range check but exceeds int64 (e.g. (float) PHP_INT_MAX, which
     * is 9223372036854775808.0 = PHP_INT_MAX + 1 due to IEEE-754 rounding)
     * would be cast through (int) and wrap around to a negative int with a
     * PHP 8.5 deprecation notice (TypeError in 9.0).
     */
    #[Test]
    public function coerce_to_integer_strict_rejects_float_at_php_int_max_boundary(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict((float) PHP_INT_MAX);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertStringContainsString('Float value out of integer range', $e->reason() ?? '');
        }
    }

    #[Test]
    public function coerce_to_integer_strict_rejects_float_at_php_int_min_boundary(): void
    {
        try {
            $this->coercer->exposedCoerceToIntegerStrict((float) PHP_INT_MIN);
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertStringContainsString('Float value out of integer range', $e->reason() ?? '');
        }
    }

    #[Test]
    public function coerce_to_number_throws_on_precision_loss_for_oversized_integer_string(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('String value loses precision when converted to float');

        $this->coercer->exposedCoerceToNumber('99999999999999999999999999');
    }

    #[Test]
    public function coerce_to_number_strict_throws_on_precision_loss_for_oversized_integer_string(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('String value loses precision when converted to float');

        $this->coercer->exposedCoerceToNumberStrict('99999999999999999999999999');
    }

    #[Test]
    public function coerce_to_number_strict_accepts_exact_int_string(): void
    {
        $result = $this->coercer->exposedCoerceToNumberStrict('42');

        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function coerce_to_number_accepts_exact_int_string(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('42');

        $this->assertSame(42.0, $result);
    }

    #[Test]
    public function coerce_to_number_accepts_decimal_string(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('3.14');

        $this->assertSame(3.14, $result);
    }

    #[Test]
    public function coerce_to_number_accepts_scientific_notation_string(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('1e5');

        $this->assertSame(100000.0, $result);
    }

    #[Test]
    public function coerce_to_number_accepts_negative_scientific_notation_string(): void
    {
        $result = $this->coercer->exposedCoerceToNumber('-2.3E-4');

        $this->assertSame(-0.00023, $result);
    }

    #[Test]
    public function coerce_to_number_throws_on_precision_loss_for_long_decimal_string(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('String value loses precision when converted to float');

        $this->coercer->exposedCoerceToNumber('1.123456789012345678901234567890');
    }

    #[Test]
    public function coerce_to_number_throws_on_precision_loss_for_negative_oversized_integer(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('String value loses precision when converted to float');

        $this->coercer->exposedCoerceToNumber('-99999999999999999999999999');
    }

    #[Test]
    public function type_mismatch_error_carries_reason_when_provided(): void
    {
        try {
            throw new TypeMismatchError(
                expected: 'integer',
                actual: '1.0E+20',
                dataPath: '/x',
                schemaPath: '/type',
                reason: 'Float value out of integer range',
            );
        } catch (TypeMismatchError $e) {
            $this->assertSame('Float value out of integer range at /x', $e->getMessage());
            $this->assertSame('Float value out of integer range', $e->reason());

            return;
        }

        $this->fail('Expected TypeMismatchError was not thrown');
    }
}

final readonly class ConcreteCoercer extends AbstractCoercer
{
    public function exposedIsValidType(mixed $value, string $type): bool
    {
        return $this->isValidType($value, $type);
    }

    public function exposedCoerceToBoolean(mixed $value): bool|int|string|float|array|null
    {
        return $this->coerceToBoolean($value);
    }

    public function exposedCoerceToBooleanStrict(mixed $value): bool|int|string|float|array|null
    {
        return $this->coerceToBooleanStrict($value);
    }

    public function exposedCoerceToInteger(mixed $value): int|string|float|bool|array|null
    {
        return $this->coerceToInteger($value);
    }

    public function exposedCoerceToIntegerStrict(mixed $value): int|string|float|bool|array|null
    {
        return $this->coerceToIntegerStrict($value);
    }

    public function exposedCoerceToNumber(mixed $value): float|int|string|bool|array|null
    {
        return $this->coerceToNumber($value);
    }

    public function exposedCoerceToNumberStrict(mixed $value): float|int|string|bool|array|null
    {
        return $this->coerceToNumberStrict($value);
    }

    public function exposedCoerceToString(mixed $value): string|int|float|bool|array|null
    {
        return $this->coerceToString($value);
    }
}
