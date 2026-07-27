<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\Internal\ArrayTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\CompositionTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\ObjectTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\ScalarTypeHelper;
use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function in_array;
use function is_array;
use function is_string;

final class TypeHelper
{
    private const array VALID_TYPES = [
        'string',
        'number',
        'integer',
        'boolean',
        'array',
        'object',
        'null',
    ];

    /** @return array<array-key, mixed> */
    public static function asArray(mixed $value): array
    {
        return ArrayTypeHelper::asArray($value);
    }

    public static function asString(mixed $value): string
    {
        return ScalarTypeHelper::asString($value);
    }

    public static function asStringOrNull(mixed $value): ?string
    {
        return ScalarTypeHelper::asStringOrNull($value);
    }

    /** @return string|array<int, string|null>|null */
    public static function asTypeOrNull(mixed $value): string|array|null
    {
        if (null === $value) {
            return null;
        }

        if (is_string($value)) {
            return self::isValidTypeString($value) ? $value : null;
        }

        if (is_array($value)) {
            /** @var list<string|null> $result */
            $result = [];
            foreach ($value as $item) {
                if (null === $item) {
                    $result[] = null;
                    continue;
                }

                if (false === is_string($item)) {
                    throw new TypeError('Expected string or null in type array, got ' . TypeFormatter::format($item));
                }

                if (false === self::isValidTypeString($item)) {
                    return null;
                }

                $result[] = $item;
            }

            return $result;
        }

        throw new TypeError('Expected string or array for type, got ' . TypeFormatter::format($value));
    }

    /** @return list<mixed> */
    public static function asList(mixed $value): array
    {
        return ArrayTypeHelper::asList($value);
    }

    /** @return list<string> */
    public static function asStringList(mixed $value): array
    {
        return ArrayTypeHelper::asStringList($value);
    }

    /** @return list<string>|null */
    public static function asStringListOrNull(mixed $value): ?array
    {
        return ArrayTypeHelper::asStringListOrNull($value);
    }

    /** @return array<string, string> */
    public static function asStringMap(mixed $value): array
    {
        return ObjectTypeHelper::asStringMap($value);
    }

    /** @return array<string, string>|null */
    public static function asStringMapOrNull(mixed $value): ?array
    {
        return ObjectTypeHelper::asStringMapOrNull($value);
    }

    /** @return array<string, mixed>|null */
    public static function asStringMixedMapOrNull(mixed $value): ?array
    {
        return ObjectTypeHelper::asStringMixedMapOrNull($value);
    }

    /** @return list<mixed> */
    public static function asEnumList(mixed $value): array
    {
        return ArrayTypeHelper::asEnumList($value);
    }

    /** @return list<mixed>|null */
    public static function asEnumListOrNull(mixed $value): ?array
    {
        return ArrayTypeHelper::asEnumListOrNull($value);
    }

    public static function asInt(mixed $value): int
    {
        return ScalarTypeHelper::asInt($value);
    }

    public static function asIntOrNull(mixed $value): ?int
    {
        return ScalarTypeHelper::asIntOrNull($value);
    }

    public static function asFloat(mixed $value): float
    {
        return ScalarTypeHelper::asFloat($value);
    }

    public static function asFloatOrNull(mixed $value): ?float
    {
        return ScalarTypeHelper::asFloatOrNull($value);
    }

    public static function asBool(mixed $value): bool
    {
        return ScalarTypeHelper::asBool($value);
    }

    public static function asBoolOrNull(mixed $value): ?bool
    {
        return ScalarTypeHelper::asBoolOrNull($value);
    }

    /** @return list<array<string, list<string>>> */
    public static function asSecurityListMap(mixed $value): array
    {
        return CompositionTypeHelper::asSecurityListMap($value);
    }

    /** @return list<array<string, list<string>>>|null */
    public static function asSecurityListMapOrNull(mixed $value): ?array
    {
        return CompositionTypeHelper::asSecurityListMapOrNull($value);
    }

    private static function isValidTypeString(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }
}
