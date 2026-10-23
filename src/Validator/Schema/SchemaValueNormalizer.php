<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use stdClass;

use function get_object_vars;
use function in_array;
use function is_array;
use function is_object;
use function is_resource;
use function is_scalar;
use function sprintf;
use function is_string;

final readonly class SchemaValueNormalizer
{
    /**
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

    public static function isNullableSchema(Schema $schema, bool $nullableAsType, ?DialectProfile $profile = null): bool
    {
        if (false === $nullableAsType) {
            return false;
        }

        if (null === $schema->type && true === ($profile?->nullableRequiresType ?? false)) {
            return false;
        }

        return $schema->nullable;
    }

    public static function allowsNull(Schema $schema, bool $nullableAsType = true, ?DialectProfile $profile = null): bool
    {
        if (false === $nullableAsType) {
            return false;
        }

        if (self::doesTypeIncludeNull($schema->type)) {
            return true;
        }

        if (null !== $schema->ref || null !== $schema->allOf || null !== $schema->anyOf || null !== $schema->oneOf) {
            return true;
        }

        if (null === $schema->type && true === ($profile?->nullableRequiresType ?? false)) {
            return false;
        }

        return $schema->nullable;
    }

    /**
     * @param string|array<int, string|null>|null $type
     */
    public static function doesTypeIncludeNull(string|array|null $type): bool
    {
        if (is_string($type)) {
            return 'null' === $type;
        }

        if (false === is_array($type)) {
            return false;
        }

        return in_array('null', $type, true) || in_array(null, $type, true);
    }
}
