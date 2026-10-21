<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Coercion;

use Duyler\OpenApi\Validator\Coercion\NumberStringNormalizer;
use Duyler\OpenApi\Validator\Exception\InvalidNumericStringException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;

final class NumberStringNormalizerTest extends TestCase
{
    public static function canonicalizeProvider(): array
    {
        return [
            'plain integer' => ['42', '42'],
            'leading plus' => ['+42', '42'],
            'leading minus' => ['-42', '-42'],
            'leading zeros' => ['00042', '42'],
            'zero' => ['0', '0'],
            'negative zero' => ['-0', '0'],
            'leading zeros zero' => ['000', '0'],
            'decimal' => ['3.14', '3.14'],
            'decimal trailing zeros' => ['3.1400', '3.14'],
            'decimal leading zeros' => ['003.14', '3.14'],
            'decimal with no integer part' => ['0.5', '0.5'],
            'scientific positive exponent' => ['1e5', '100000'],
            'scientific plus exponent' => ['1e+5', '100000'],
            'scientific negative exponent' => ['2.3E-4', '0.00023'],
            'scientific decimal mantissa' => ['1.5e10', '15000000000'],
            'scientific zero exponent' => ['5e0', '5'],
            'scientific big exponent' => ['1e20', '100000000000000000000'],
            'oversized integer preserved' => ['99999999999999999999999999', '99999999999999999999999999'],
            'negative oversized' => ['-99999999999999999999999999', '-99999999999999999999999999'],
        ];
    }

    #[DataProvider('canonicalizeProvider')]
    #[Test]
    public function canonicalize_returns_expected_form(string $input, string $expected): void
    {
        $this->assertSame($expected, NumberStringNormalizer::canonicalize($input));
    }

    public static function invalidProvider(): array
    {
        return [
            'empty string' => [''],
            'hex notation' => ['0x10'],
            'trailing chars' => ['12abc'],
            'leading whitespace' => [' 42'],
            'two decimals' => ['1.2.3'],
            'two exponents' => ['1e2e3'],
            'lone dot' => ['.'],
            'lone sign' => ['+'],
            'lone exponent' => ['e5'],
        ];
    }

    #[DataProvider('invalidProvider')]
    #[Test]
    public function canonicalize_returns_null_for_invalid_input(string $input): void
    {
        $this->assertNull(NumberStringNormalizer::canonicalize($input));
    }

    #[Test]
    public function canonicalize_rejects_huge_exponent(): void
    {
        $this->expectException(InvalidNumericStringException::class);
        $this->expectExceptionMessage('Scientific notation exponent 999999999 exceeds double precision boundary 320.');

        NumberStringNormalizer::canonicalize('1e999999999');
    }

    #[Test]
    public function canonicalize_rejects_huge_negative_exponent(): void
    {
        $this->expectException(InvalidNumericStringException::class);
        $this->expectExceptionMessage('Scientific notation exponent -999999999 exceeds double precision boundary 320.');

        NumberStringNormalizer::canonicalize('1e-999999999');
    }

    #[Test]
    public function canonicalize_round_trips_small_integer_through_float(): void
    {
        $input = '42';
        $float = (float) $input;

        $this->assertSame(
            NumberStringNormalizer::canonicalize($input),
            NumberStringNormalizer::canonicalize((string) $float),
        );
    }

    #[Test]
    public function canonicalize_differs_for_oversized_integer_through_float(): void
    {
        $input = '99999999999999999999999999';
        $float = (float) $input;

        $this->assertNotSame(
            NumberStringNormalizer::canonicalize($input),
            NumberStringNormalizer::canonicalize((string) $float),
        );
    }

    #[Test]
    public function canonicalize_round_trips_scientific_notation(): void
    {
        $input = '1.5e10';
        $float = (float) $input;

        $this->assertSame(
            NumberStringNormalizer::canonicalize($input),
            NumberStringNormalizer::canonicalize((string) $float),
        );
    }

    #[Test]
    public function canonicalize_round_trips_small_negative_scientific(): void
    {
        $input = '-2.3E-4';
        $float = (float) $input;

        $this->assertSame(
            NumberStringNormalizer::canonicalize($input),
            NumberStringNormalizer::canonicalize((string) $float),
        );
    }

    #[Test]
    public function cast_string_to_float_returns_float_for_simple_integer(): void
    {
        $this->assertSame(42.0, NumberStringNormalizer::castStringToFloatOrFail('42'));
    }

    #[Test]
    public function cast_string_to_float_returns_negative_float_for_signed_integer(): void
    {
        $this->assertSame(-42.0, NumberStringNormalizer::castStringToFloatOrFail('-42'));
    }

    #[Test]
    public function cast_string_to_float_returns_float_for_decimal(): void
    {
        $this->assertSame(3.14, NumberStringNormalizer::castStringToFloatOrFail('3.14'));
    }

    #[Test]
    public function cast_string_to_float_returns_float_for_scientific_decimal_mantissa(): void
    {
        $this->assertSame(1500.0, NumberStringNormalizer::castStringToFloatOrFail('1.5e3'));
    }

    #[Test]
    public function cast_string_to_float_returns_float_for_scientific_big_exponent(): void
    {
        $result = NumberStringNormalizer::castStringToFloatOrFail('1e20');

        $this->assertSame(1.0E+20, $result);
    }

    #[Test]
    public function cast_string_to_float_returns_float_for_representable_scientific_at_boundary(): void
    {
        $this->assertSame(1.0E+308, NumberStringNormalizer::castStringToFloatOrFail('1e308'));
    }

    #[Test]
    public function cast_string_to_float_throws_on_exponent_above_320(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value is outside the representable range of a double');

        NumberStringNormalizer::castStringToFloatOrFail('1e321');
    }

    #[Test]
    public function cast_string_to_float_throws_on_negative_exponent_above_320(): void
    {
        $this->expectException(TypeMismatchError::class);

        NumberStringNormalizer::castStringToFloatOrFail('1e-321');
    }

    #[Test]
    public function cast_string_to_float_rejects_at_exponent_boundary_320_with_inf_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value overflows IEEE-754 double range (INF)');

        NumberStringNormalizer::castStringToFloatOrFail('1e320');
    }

    #[Test]
    public function cast_string_to_float_rejects_1e309_with_inf_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value overflows IEEE-754 double range (INF)');

        NumberStringNormalizer::castStringToFloatOrFail('1e309');
    }

    #[Test]
    public function cast_string_to_float_rejects_negative_1e309_with_inf_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value overflows IEEE-754 double range (INF)');

        NumberStringNormalizer::castStringToFloatOrFail('-1e309');
    }

    #[Test]
    public function cast_string_to_float_rejects_1e310_with_inf_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value overflows IEEE-754 double range (INF)');

        NumberStringNormalizer::castStringToFloatOrFail('1e310');
    }

    #[Test]
    public function cast_string_to_float_rejects_php_float_max_plus_one_with_inf_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value overflows IEEE-754 double range (INF)');

        NumberStringNormalizer::castStringToFloatOrFail('1.8e308');
    }

    #[Test]
    public function cast_string_to_float_rejects_subnormal_boundary_with_range_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value is outside the representable range of a double');

        NumberStringNormalizer::castStringToFloatOrFail('0.1e-320');
    }

    #[Test]
    public function cast_string_to_float_rejects_deep_subnormal_with_range_reason(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Numeric value is outside the representable range of a double');

        NumberStringNormalizer::castStringToFloatOrFail('0.001e-320');
    }

    #[Test]
    public function cast_string_to_float_throws_on_oversized_integer_string(): void
    {
        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('String value loses precision when converted to float');

        NumberStringNormalizer::castStringToFloatOrFail('99999999999999999999999999');
    }

    #[Test]
    public function cast_string_to_float_throws_on_negative_oversized_integer_string(): void
    {
        $this->expectException(TypeMismatchError::class);

        NumberStringNormalizer::castStringToFloatOrFail('-99999999999999999999999999');
    }

    #[Test]
    public function cast_string_to_float_throws_on_php_int_max_string_precision_loss(): void
    {
        $this->expectException(TypeMismatchError::class);

        NumberStringNormalizer::castStringToFloatOrFail((string) PHP_INT_MAX);
    }

    #[Test]
    public function cast_string_to_float_propagates_data_path_in_dos_exception(): void
    {
        try {
            NumberStringNormalizer::castStringToFloatOrFail('1e321', '/data/path');
            $this->fail('Expected TypeMismatchError was not thrown');
        } catch (TypeMismatchError $e) {
            $this->assertSame('/data/path', $e->dataPath());
        }
    }

    #[Test]
    public function cast_string_to_float_passes_nan_string_through_as_zero(): void
    {
        $this->assertSame(0.0, NumberStringNormalizer::castStringToFloatOrFail('NaN'));
    }

    #[Test]
    public function cast_string_to_float_passes_inf_string_through_as_zero(): void
    {
        $this->assertSame(0.0, NumberStringNormalizer::castStringToFloatOrFail('Inf'));
    }
}
