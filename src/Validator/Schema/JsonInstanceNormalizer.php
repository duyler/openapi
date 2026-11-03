<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use stdClass;

use function array_is_list;
use function array_map;
use function get_object_vars;
use function is_array;

final readonly class JsonInstanceNormalizer
{
    public static function normalize(mixed $data): mixed
    {
        if ($data instanceof stdClass) {
            return self::normalizeObject(get_object_vars($data));
        }

        if (is_array($data)) {
            return self::normalizeArray($data);
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return JsonContainer|array<array-key, mixed>
     */
    private static function normalizeObject(array $values): JsonContainer|array
    {
        if ([] === $values) {
            return new JsonContainer(JsonContainerKind::Object, []);
        }

        $normalized = array_map(self::normalize(...), $values);

        if (array_is_list($normalized)) {
            return new JsonContainer(JsonContainerKind::Object, $normalized);
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return JsonContainer|array<array-key, mixed>
     */
    private static function normalizeArray(array $values): JsonContainer|array
    {
        if ([] === $values) {
            return new JsonContainer(JsonContainerKind::Array, []);
        }

        return array_map(self::normalize(...), $values);
    }
}
