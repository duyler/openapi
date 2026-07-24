<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use stdClass;

use function get_object_vars;
use function in_array;
use function is_array;
use function is_object;
use function is_resource;
use function is_scalar;
use function sprintf;

final readonly class SchemaValueNormalizer
{
    /**
     * @throws InvalidDataTypeException
     *
     * @return array<int|string, mixed>|int|string|float|bool|null
     */
    public static function normalize(mixed $value, bool $allowNull = false): array|int|string|float|bool|null
    {
        if (null === $value && $allowNull) {
            return $value;
        }

        if (is_array($value) || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof stdClass) {
            /** @var array<int|string, mixed> */
            return get_object_vars($value);
        }

        $typeDescription = match (true) {
            is_object($value) => 'object (' . $value::class . ')',
            is_resource($value) => 'resource',
            null === $value => 'null',
            default => 'unknown',
        };

        throw new InvalidDataTypeException(sprintf(
            'Data must be array, int, string, float or bool, %s given',
            $typeDescription,
        ));
    }

    /**
     * @param string|array<int, string|null>|null $type
     */
    public static function typeIncludesNull(string|array|null $type): bool
    {
        if (false === is_array($type)) {
            return false;
        }

        return in_array('null', $type, true) || in_array(null, $type, true);
    }
}
