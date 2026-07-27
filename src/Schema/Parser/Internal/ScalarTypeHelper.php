<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final readonly class ScalarTypeHelper
{
    public static function asString(mixed $value): string
    {
        if (false === is_string($value)) {
            throw new TypeError('Expected string, got ' . TypeFormatter::format($value));
        }

        return $value;
    }

    public static function asStringOrNull(mixed $value): ?string
    {
        return null === $value ? null : self::asString($value);
    }

    public static function asInt(mixed $value): int
    {
        if (false === is_int($value)) {
            throw new TypeError('Expected int, got ' . TypeFormatter::format($value));
        }

        return $value;
    }

    public static function asIntOrNull(mixed $value): ?int
    {
        return null === $value ? null : self::asInt($value);
    }

    public static function asFloat(mixed $value): float
    {
        if (false === is_float($value) && false === is_int($value)) {
            throw new TypeError('Expected float, got ' . TypeFormatter::format($value));
        }

        return (float) $value;
    }

    public static function asFloatOrNull(mixed $value): ?float
    {
        return null === $value ? null : self::asFloat($value);
    }

    public static function asBool(mixed $value): bool
    {
        if (false === is_bool($value)) {
            throw new TypeError('Expected bool, got ' . TypeFormatter::format($value));
        }

        return $value;
    }

    public static function asBoolOrNull(mixed $value): ?bool
    {
        return null === $value ? null : self::asBool($value);
    }
}
