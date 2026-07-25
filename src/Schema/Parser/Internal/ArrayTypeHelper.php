<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function array_values;
use function is_array;
use function is_string;

/** @internal */
final readonly class ArrayTypeHelper
{
    public static function asArray(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected array, got ' . TypeFormatter::format($value));
        }

        return $value;
    }

    /** @return list<mixed> */
    public static function asList(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected array, got ' . TypeFormatter::format($value));
        }

        /** @var list<mixed> $value */
        return array_values($value);
    }

    /** @return list<string> */
    public static function asStringList(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected list, got ' . TypeFormatter::format($value));
        }

        $result = [];
        foreach ($value as $item) {
            if (false === is_string($item)) {
                throw new TypeError('Expected string in list, got ' . TypeFormatter::format($item));
            }
            $result[] = $item;
        }

        /** @var list<string> $result */
        return $result;
    }

    /** @return list<string>|null */
    public static function asStringListOrNull(mixed $value): ?array
    {
        return null === $value ? null : self::asStringList($value);
    }

    /** @return list<mixed> */
    public static function asEnumList(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected enum list, got ' . TypeFormatter::format($value));
        }

        return array_values($value);
    }

    /** @return list<mixed>|null */
    public static function asEnumListOrNull(mixed $value): ?array
    {
        return null === $value ? null : self::asEnumList($value);
    }
}
