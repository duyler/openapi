<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Validator\TypeFormatter;
use TypeError;

use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class TypeHelper
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

    /**
     * @param mixed $value
     * @return array<array-key, mixed>
     * @throws TypeError
     */
    public static function asArray(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected array, got ' . TypeFormatter::format($value));
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return string
     * @throws TypeError
     */
    public static function asString(mixed $value): string
    {
        if (false === is_string($value)) {
            throw new TypeError('Expected string, got ' . TypeFormatter::format($value));
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return string|null
     * @throws TypeError
     */
    public static function asStringOrNull(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        return self::asString($value);
    }

    /**
     * @param mixed $value
     * @return string|array<int, string|null>|null
     * @throws TypeError
     */
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

    /**
     * @param mixed $value
     * @return array<array-key, mixed>
     * @throws TypeError
     */
    public static function asList(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected array, got ' . TypeFormatter::format($value));
        }

        return array_values($value);
    }

    /**
     * @param mixed $value
     * @return list<string>
     * @throws TypeError
     */
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

    /**
     * @param mixed $value
     * @return list<string>|null
     * @throws TypeError
     */
    public static function asStringListOrNull(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        return self::asStringList($value);
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     * @throws TypeError
     */
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

    /**
     * @param mixed $value
     * @return array<string, string>|null
     * @throws TypeError
     */
    public static function asStringMapOrNull(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        return self::asStringMap($value);
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>|null
     * @throws TypeError
     */
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

    /**
     * @param mixed $value
     * @return list<mixed>
     * @throws TypeError
     */
    public static function asEnumList(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected enum list, got ' . TypeFormatter::format($value));
        }
        return array_values($value);
    }

    /**
     * @param mixed $value
     * @return list<mixed>|null
     * @throws TypeError
     */
    public static function asEnumListOrNull(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        return self::asEnumList($value);
    }

    /**
     * @param mixed $value
     * @return int
     * @throws TypeError
     */
    public static function asInt(mixed $value): int
    {
        if (false === is_int($value)) {
            throw new TypeError('Expected int, got ' . TypeFormatter::format($value));
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return int|null
     * @throws TypeError
     */
    public static function asIntOrNull(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }
        return self::asInt($value);
    }

    /**
     * @param mixed $value
     * @return float
     * @throws TypeError
     */
    public static function asFloat(mixed $value): float
    {
        if (false === is_float($value) && !is_int($value)) {
            throw new TypeError('Expected float, got ' . TypeFormatter::format($value));
        }
        return (float) $value;
    }

    /**
     * @param mixed $value
     * @return float|null
     * @throws TypeError
     */
    public static function asFloatOrNull(mixed $value): ?float
    {
        if (null === $value) {
            return null;
        }
        return self::asFloat($value);
    }

    /**
     * @param mixed $value
     * @return bool
     * @throws TypeError
     */
    public static function asBool(mixed $value): bool
    {
        if (false === is_bool($value)) {
            throw new TypeError('Expected bool, got ' . TypeFormatter::format($value));
        }
        return $value;
    }

    /**
     * @param mixed $value
     * @return bool|null
     * @throws TypeError
     */
    public static function asBoolOrNull(mixed $value): ?bool
    {
        if (null === $value) {
            return null;
        }
        return self::asBool($value);
    }

    /**
     * @param mixed $value
     * @return list<array<string, list<string>>>
     * @throws TypeError
     */
    public static function asSecurityListMap(mixed $value): array
    {
        if (false === is_array($value)) {
            throw new TypeError('Expected security list map, got ' . TypeFormatter::format($value));
        }

        $result = [];
        foreach ($value as $item) {
            if (false === is_array($item)) {
                throw new TypeError('Expected array in security list, got ' . TypeFormatter::format($item));
            }

            /** @var array<string, list<string>> $securityItem */
            $securityItem = [];
            foreach ($item as $key => $val) {
                if (false === is_string($key)) {
                    throw new TypeError('Expected string key in security map, got ' . TypeFormatter::format($key));
                }
                if (false === is_array($val)) {
                    throw new TypeError('Expected list in security map value, got ' . TypeFormatter::format($val));
                }
                /** @var list<string> $val */
                $val = self::asStringList($val);
                $securityItem[$key] = $val;
            }
            $result[] = $securityItem;
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return list<array<string, list<string>>>|null
     * @throws TypeError
     */
    public static function asSecurityListMapOrNull(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        return self::asSecurityListMap($value);
    }

    private static function isValidTypeString(string $type): bool
    {
        return in_array($type, self::VALID_TYPES, true);
    }
}
