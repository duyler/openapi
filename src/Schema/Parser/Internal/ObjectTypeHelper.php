<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function is_array;
use function is_string;
use function is_bool;

/** @internal */
final readonly class ObjectTypeHelper
{
    /** @return array<string, string> */
    public static function asStringMap(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected string map, got ' . TypeFormatter::format($value));
        }

        foreach ($value as $key => $val) {
            if (false === is_string($key)) {
                throw new TypeError('Expected string key in map, got ' . TypeFormatter::format($key));
            }
            if (false === is_string($val)) {
                throw new TypeError('Expected string value in map, got ' . TypeFormatter::format($val));
            }
        }

        /** @var array<string, string> $value */
        return $value;
    }

    /** @return array<string, string>|null */
    public static function asStringMapOrNull(mixed $value): ?array
    {
        return null === $value ? null : self::asStringMap($value);
    }

    /** @return array<string, bool> */
    public static function asBoolMap(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected bool map, got ' . TypeFormatter::format($value));
        }

        foreach ($value as $key => $val) {
            if (false === is_string($key)) {
                throw new TypeError('Expected string key in map, got ' . TypeFormatter::format($key));
            }
            if (false === is_bool($val)) {
                throw new TypeError('Expected bool value in map, got ' . TypeFormatter::format($val));
            }
        }

        /** @var array<string, bool> $value */
        return $value;
    }

    /** @return array<string, bool>|null */
    public static function asBoolMapOrNull(mixed $value): ?array
    {
        return null === $value ? null : self::asBoolMap($value);
    }

    /** @return array<string, mixed>|null */
    public static function asStringMixedMapOrNull(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }

        if (false === is_array($value)) {
            throw new TypeError('Expected string mixed map, got ' . TypeFormatter::format($value));
        }

        foreach ($value as $key => $_) {
            if (false === is_string($key)) {
                throw new TypeError('Expected string key in mixed map, got ' . TypeFormatter::format($key));
            }
        }

        /** @var array<string, mixed> $value */
        return $value;
    }
}
