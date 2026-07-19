<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Coercion;

use Duyler\OpenApi\Validator\Coercion\NumberStringNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
