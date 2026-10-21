<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use function ltrim;
use function substr;

final readonly class IntegerStringNormalizer
{
    public const string INTEGER_PATTERN = '/^[+-]?\d+$/';

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
