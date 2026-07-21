<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function abs;
use function explode;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strlen;
use function substr;

final readonly class NumberStringNormalizer
{
    public const string NUMBER_PATTERN = '/^[+-]?\\d+(\\.\\d+)?([eE][+-]?\\d+)?$/';

    /**
     * Returns the canonical decimal form of a numeric string, or null if the
     * input is not a recognised integer/decimal/scientific value.
     *
     * Used to detect silent precision loss of `(float) $value` cast: a value
     * that passes the coerceToNumber regex but whose canonical form does not
     * equal the canonical form of the round-tripped float loses precision
     * when coerced to float.
     */
    public static function canonicalize(string $value): ?string
    {
        if (1 !== preg_match(self::NUMBER_PATTERN, $value)) {
            return null;
        }

        $sign = '';
        $unsigned = $value;

        if (str_starts_with($value, '-')) {
            $sign = '-';
            $unsigned = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $unsigned = substr($value, 1);
        }

        $expanded = self::expandScientificNotation($unsigned);

        return self::normalizeDecimal($sign, $expanded);
    }

    /**
     * Casts a numeric string to float and rejects values that lose precision
     * when round-tripped through IEEE-754 double. Detects precision loss
     * visible at the string representation level (e.g., 17+ significant
     * digits). Does NOT prevent magic-hash type-juggling collisions for the
     * 0eNNN pattern (both inputs canonicalize to "0" and pass) - that
     * requires strict equality in downstream comparisons, not coercion-time
     * rejection.
     *
     * Also rejects scientific-notation values whose exponent exceeds the
     * representable range of a double BEFORE invoking canonicalize, which
     * would otherwise expand them via str_repeat and trigger algorithmic
     * DoS (e.g. `1e999999999` would allocate ~1 GB of zeros).
     *
     * @throws TypeMismatchError when the input cannot be represented as float
     *     without precision loss, or when its scientific-notation exponent
     *     exceeds the representable range of a double.
     */
    public static function castStringToFloatOrFail(string $value, string $dataPath = ''): float
    {
        if (1 === preg_match('/^[+-]?\\d+(?:\\.\\d+)?[eE](?<exponent>[+-]?\\d+)$/', $value, $matches)) {
            $exponent = (int) $matches['exponent'];
            if (abs($exponent) > 320) {
                throw new TypeMismatchError(
                    expected: 'number',
                    actual: $value,
                    dataPath: $dataPath,
                    schemaPath: '/type',
                    reason: 'Numeric value is outside the representable range of a double',
                );
            }
        }

        $float = (float) $value;
        $inputCanonical = self::canonicalize($value);
        $roundtripCanonical = self::canonicalize((string) $float);

        if (null !== $inputCanonical && null !== $roundtripCanonical && $inputCanonical !== $roundtripCanonical) {
            throw new TypeMismatchError(
                expected: 'number',
                actual: $value,
                dataPath: $dataPath,
                schemaPath: '/type',
                reason: 'String value loses precision when converted to float',
            );
        }

        return $float;
    }

    private static function expandScientificNotation(string $value): string
    {
        if (1 !== preg_match('/^(?<mantissa>\\d+(?:\\.\\d+)?)[eE](?<exponent>[+-]?\\d+)$/', $value, $matches)) {
            return $value;
        }

        $mantissa = $matches['mantissa'];
        $exponent = (int) $matches['exponent'];

        if (str_contains($mantissa, '.')) {
            [$integer, $fraction] = explode('.', $mantissa);
        } else {
            $integer = $mantissa;
            $fraction = '';
        }

        $digits = $integer . $fraction;
        $decimalPos = strlen($integer) + $exponent;

        if ($decimalPos <= 0) {
            $digits = str_repeat('0', -$decimalPos) . $digits;
            $decimalPos = 0;
        } elseif ($decimalPos >= strlen($digits)) {
            $digits = $digits . str_repeat('0', $decimalPos - strlen($digits));
        }

        $integerPart = substr($digits, 0, $decimalPos);
        $fractionPart = substr($digits, $decimalPos);

        if ('' === $integerPart) {
            $integerPart = '0';
        }

        return '' === $fractionPart ? $integerPart : $integerPart . '.' . $fractionPart;
    }

    private static function normalizeDecimal(string $sign, string $unsigned): string
    {
        if (str_contains($unsigned, '.')) {
            [$integer, $fraction] = explode('.', $unsigned);
        } else {
            $integer = $unsigned;
            $fraction = '';
        }

        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        if ('0' === $integer && '' === $fraction) {
            return '0';
        }

        $canonical = '' === $fraction ? $integer : sprintf('%s.%s', $integer, $fraction);

        return '-' === $sign ? '-' . $canonical : $canonical;
    }
}
