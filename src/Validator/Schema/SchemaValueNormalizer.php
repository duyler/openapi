<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
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

    public static function isNullableSchema(Schema $schema, bool $nullableAsType): bool
    {
        return $nullableAsType && $schema->nullable;
    }

    /**
     * Decides whether the pre-check may hand a null to $schema.
     *
     * A composition or $ref node carries neither type nor nullable of its
     * own — both live in the branches or in the resolved target — so null
     * is deferred to them instead of being rejected here.
     */
    public static function allowsNull(Schema $schema, bool $nullableAsType = true): bool
    {
        if (false === $nullableAsType) {
            return false;
        }

        return $schema->nullable
            || self::doesTypeIncludeNull($schema->type)
            || null !== $schema->ref
            || null !== $schema->allOf
            || null !== $schema->anyOf
            || null !== $schema->oneOf;
    }

    /**
     * @param string|array<int, string|null>|null $type
     */
    public static function doesTypeIncludeNull(string|array|null $type): bool
    {
        if (false === is_array($type)) {
            return false;
        }

        return in_array('null', $type, true) || in_array(null, $type, true);
    }
}
