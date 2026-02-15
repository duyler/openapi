<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;

readonly class RequestBodyCoercer
{
    public function coerce(mixed $value, ?Schema $schema, bool $enabled, bool $strict = false, bool $nullableAsType = true): mixed
    {
        if (false === $enabled || null === $schema) {
            return $value;
        }

        if (null === $value && $schema->nullable && $nullableAsType) {
            return $value;
        }

        $type = $schema->type;

        if (null === $type) {
            return $value;
        }

        if (is_array($type)) {
            return $this->coerceUnionType($value, $type, $schema, $strict, $nullableAsType);
        }

        return $this->coerceToType($value, $type, $schema, $strict, $nullableAsType);
    }

    private function coerceUnionType(mixed $value, array $types, Schema $schema, bool $strict, bool $nullableAsType): mixed
    {
        foreach ($types as $type) {
            if (!is_string($type) || 'null' === $type) {
                continue;
            }

            $coerced = $this->coerceToType($value, $type, $schema, $strict, $nullableAsType);

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        return $value;
    }

    private function coerceToType(mixed $value, string $type, Schema $schema, bool $strict, bool $nullableAsType): mixed
    {
        return match ($type) {
            'string' => $this->coerceToString($value),
            'integer' => $this->coerceToInteger($value, $strict),
            'number' => $this->coerceToNumber($value, $strict),
            'boolean' => $this->coerceToBoolean($value),
            'object' => $this->coerceToObject($value, $schema, $strict, $nullableAsType),
            'array' => $this->coerceToArray($value, $schema, $strict, $nullableAsType),
            default => $value,
        };
    }

    private function coerceToString(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $value;
    }

    private function coerceToInteger(mixed $value, bool $strict): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if ($strict && !is_numeric($value) || (string) (int) $value !== $value) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            $coerced = (int) $value;

            if ((string) $coerced !== $value) {
                return (int) $value;
            }

            return $coerced;
        }

        if (is_float($value)) {
            if ($strict) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: (string) $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    private function coerceToNumber(mixed $value, bool $strict): mixed
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            if ($strict && !is_numeric($value)) {
                throw new TypeMismatchError(
                    expected: 'number',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            return (float) $value;
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        return $value;
    }

    private function coerceToBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            return match ($lower) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => (bool) $value,
            };
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_float($value)) {
            return $value !== 0.0;
        }

        return $value;
    }

    private function coerceToObject(mixed $value, Schema $schema, bool $strict, bool $nullableAsType): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $properties = $schema->properties;

        if (null === $properties) {
            return $value;
        }

        $coerced = $value;

        foreach ($properties as $name => $propertySchema) {
            if (!isset($value[$name])) {
                continue;
            }

            $coerced[$name] = $this->coerce($value[$name], $propertySchema, true, $strict, $nullableAsType);
        }

        return $coerced;
    }

    private function coerceToArray(mixed $value, Schema $schema, bool $strict, bool $nullableAsType): array
    {
        if (!is_array($value)) {
            return [];
        }

        $itemsSchema = $schema->items;

        if (null === $itemsSchema) {
            return $value;
        }

        $coerced = [];

        foreach ($value as $item) {
            $coerced[] = $this->coerce($item, $itemsSchema, true, $strict, $nullableAsType);
        }

        return $coerced;
    }

    private function isValidType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_float($value) || is_int($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'null' => null === $value,
            'object' => is_array($value),
            'array' => is_array($value),
            default => true,
        };
    }
}
