<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Coercion;

use Duyler\OpenApi\Validator\Coercion\IntegerStringNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class IntegerStringNormalizerTest extends TestCase
{
    public static function canonicalizeProvider(): array
    {
        return [
            'plain integer' => ['42', '42'],
            'leading plus' => ['+42', '42'],
            'leading minus' => ['-42', '-42'],
            'leading zeros' => ['00042', '42'],
            'leading zeros with sign' => ['-00042', '-42'],
            'plus leading zeros' => ['+00042', '42'],
            'zero' => ['0', '0'],
            'negative zero' => ['-0', '0'],
            'plus zero' => ['+0', '0'],
            'leading zeros zero' => ['000', '0'],
            'negative leading zeros zero' => ['-000', '0'],
            'oversized integer preserved' => ['99999999999999999999', '99999999999999999999'],
            'negative oversized preserved' => ['-99999999999999999999', '-99999999999999999999'],
            'php int max' => [(string) PHP_INT_MAX, (string) PHP_INT_MAX],
            'php int min' => [(string) PHP_INT_MIN, (string) PHP_INT_MIN],
            'one above php int max' => ['9223372036854775808', '9223372036854775808'],
            'one below php int min' => ['-9223372036854775809', '-9223372036854775809'],
        ];
    }

    #[DataProvider('canonicalizeProvider')]
    #[Test]
    public function canonicalize_returns_expected_form(string $input, string $expected): void
    {
        $this->assertSame($expected, IntegerStringNormalizer::canonicalize($input));
    }

    #[Test]
    public function canonicalize_round_trips_in_int64_range(): void
    {
        // The SEC-15 contract: for any value matching /^[+-]?\d+$/ inside the
        // int64 range, canonicalize($v) must equal (string) (int) $v. Equality
        // is what AbstractCoercer relies on to accept the coerced int.
        foreach (['0', '42', '-42', '+42', '00042', (string) PHP_INT_MAX, (string) PHP_INT_MIN] as $value) {
            $this->assertSame(
                (string) (int) $value,
                IntegerStringNormalizer::canonicalize($value),
                "Round-trip failed for: {$value}",
            );
        }
    }

    #[Test]
    public function canonicalize_differs_from_cast_for_overflow(): void
    {
        // The SEC-15 contract: a value outside int64 range canonicalizes to
        // its literal form, which MUST differ from the (int)-cast string so
        // AbstractCoercer can detect overflow and bail out.
        foreach (['99999999999999999999', '-99999999999999999999', '9223372036854775808'] as $value) {
            $this->assertNotSame(
                (string) (int) $value,
                IntegerStringNormalizer::canonicalize($value),
                "Overflow detection would fail for: {$value}",
            );
        }
    }
}
