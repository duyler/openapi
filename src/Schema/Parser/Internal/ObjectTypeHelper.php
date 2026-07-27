<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function is_array;
use function is_string;

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
