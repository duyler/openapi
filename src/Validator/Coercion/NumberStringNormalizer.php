<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use Duyler\OpenApi\Validator\Exception\InvalidNumericStringException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function abs;
use function explode;
use function is_infinite;
use function is_nan;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr;

final readonly class NumberStringNormalizer
{
    public const string NUMBER_PATTERN = '/^[+-]?\\d+(\\.\\d+)?([eE][+-]?\\d+)?$/';

    private const int MAX_DOUBLE_EXPONENT = 320;

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

    public static function castStringToFloatOrFail(string $value, string $dataPath = ''): float
    {
        $inputCanonical = self::canonicalizeOrThrow($value, $dataPath);

        $float = (float) $value;

        if (is_infinite($float)) {
            throw new TypeMismatchError(
                expected: 'number',
                actual: $value,
                dataPath: $dataPath,
                schemaPath: '/type',
                reason: 'Numeric value overflows IEEE-754 double range (INF)',
            );
        }

        if (is_nan($float)) {
            throw new TypeMismatchError(
                expected: 'number',
                actual: $value,
                dataPath: $dataPath,
                schemaPath: '/type',
                reason: 'Numeric value is NaN',
            );
        }

        $roundtripCanonical = self::canonicalizeOrThrow((string) $float, $dataPath);

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

    private static function canonicalizeOrThrow(string $value, string $dataPath): ?string
    {
        try {
            return self::canonicalize($value);
        } catch (InvalidNumericStringException) {
            throw new TypeMismatchError(
                expected: 'number',
                actual: $value,
                dataPath: $dataPath,
                schemaPath: '/type',
                reason: 'Numeric value is outside the representable range of a double',
            );
        }
    }

    private static function expandScientificNotation(string $value): string
    {
        if (1 !== preg_match('/^(?<mantissa>\\d+(?:\\.\\d+)?)[eE](?<exponent>[+-]?\\d+)$/', $value, $matches)) {
            return $value;
        }

        $mantissa = $matches['mantissa'];
        $exponent = (int) $matches['exponent'];

        if (abs($exponent) > self::MAX_DOUBLE_EXPONENT) {
            throw new InvalidNumericStringException(sprintf(
                'Scientific notation exponent %d exceeds double precision boundary %d.',
                $exponent,
                self::MAX_DOUBLE_EXPONENT,
            ));
        }

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
