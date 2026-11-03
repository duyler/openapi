<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use function array_is_list;
use function assert;
use function is_array;

final readonly class JsonShape
{
    /**
     * Returns the property map when the instance behaves as a JSON object,
     * null otherwise. A plain empty PHP array is treated as an object
     * because decoded production data loses the JSON distinction.
     *
     * @return array<array-key, mixed>|null
     */
    public static function objectValues(mixed $data): ?array
    {
        if ($data instanceof JsonContainer) {
            return $data->isObject() ? $data->values : null;
        }

        if (false === is_array($data)) {
            return null;
        }

        if ([] === $data || false === array_is_list($data)) {
            return $data;
        }

        return null;
    }

    /**
     * Returns the item list when the instance behaves as a JSON array,
     * null otherwise. A plain empty PHP array is treated as an array
     * because decoded production data loses the JSON distinction.
     *
     * @return list<mixed>|null
     */
    public static function arrayValues(mixed $data): ?array
    {
        if ($data instanceof JsonContainer) {
            $values = $data->isArray() ? $data->values : null;

            if (null !== $values) {
                assert(array_is_list($values));
            }

            return $values;
        }

        if (false === is_array($data)) {
            return null;
        }

        if ([] === $data || array_is_list($data)) {
            return $data;
        }

        return null;
    }

    public static function isObjectInstance(mixed $data): bool
    {
        return null !== self::objectValues($data);
    }

    public static function isArrayInstance(mixed $data): bool
    {
        return null !== self::arrayValues($data);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function containerValues(mixed $data): array
    {
        if ($data instanceof JsonContainer) {
            return $data->values;
        }

        assert(is_array($data));

        return $data;
    }
}
