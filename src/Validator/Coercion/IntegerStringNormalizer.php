<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use function ltrim;
use function substr;

final readonly class IntegerStringNormalizer
{
    /**
     * Returns the canonical form of an integer string: optional leading minus,
     * no leading plus, no leading zeros (except for the value zero itself).
     *
     * Used to detect silent overflow of `(int) $value` cast: a value that
     * passes `/^[+-]?\d+$/` but whose canonical form does not equal
     * `(string) (int) $value` is out of int64 range.
     */
    public static function canonicalize(string $value): string
    {
        $first = $value[0];
        $unsigned = ('+' === $first || '-' === $first) ? substr($value, 1) : $value;
        $digits = ltrim($unsigned, '0') ?: '0';

        if ('0' === $digits) {
            return '0';
        }

        return ('-' === $first ? '-' : '') . $digits;
    }
}
