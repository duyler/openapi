<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;

use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

readonly class SchemaValueNormalizer
{
    /**
     * Normalize data to match SchemaValidatorInterface requirements
     *
     * @throws InvalidDataTypeException if value is not one of supported types
     *
     * @return array<int|string, mixed>|int|string|float|bool|null
     */
    public static function normalize(mixed $value, bool $allowNull = false): array|int|string|float|bool|null
    {
        if (null === $value && $allowNull) {
            return $value;
        }

        if (is_array($value) || is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw new InvalidDataTypeException(sprintf(
            'Data must be array, int, string, float or bool, %s given',
            get_debug_type($value),
        ));
    }

    /**
     * Check if type is an array that includes 'null' or null
     */
    public static function typeIncludesNull(string|array|null $type): bool
    {
        if (is_array($type)) {
            return in_array('null', $type, true) || in_array(null, $type, true);
        }

        return false;
    }
}
