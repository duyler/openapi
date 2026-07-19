<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

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
