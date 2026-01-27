<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Schema;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class ResponseTypeCoercer
{
    public function coerce(mixed $value, ?Schema $schema, bool $enabled, bool $nullableAsType = true): mixed
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
            return $this->coerceUnionType($value, $type, $schema, $nullableAsType);
        }

        return $this->coerceToType($value, $type, $schema, $nullableAsType);
    }

    private function coerceUnionType(mixed $value, array $types, Schema $schema, bool $nullableAsType): mixed
    {
        foreach ($types as $type) {
            if (!is_string($type) || 'null' === $type) {
                continue;
            }

            $coerced = $this->coerceToType($value, $type, $schema, $nullableAsType);

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        return $value;
    }

    private function coerceToType(mixed $value, string $type, Schema $schema, bool $nullableAsType): mixed
    {
        return match ($type) {
            'string' => $this->coerceToString($value),
            'integer' => $this->coerceToInteger($value),
            'number' => $this->coerceToNumber($value),
            'boolean' => $this->coerceToBoolean($value),
            'object' => $this->coerceToObject($value, $schema, $nullableAsType),
            'array' => $this->coerceToArray($value, $schema, $nullableAsType),
            default => $value,
        };
    }

    private function coerceToString(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return $value;
    }

    private function coerceToInteger(mixed $value): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $coerced = (int) $value;

            return $coerced;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    private function coerceToNumber(mixed $value): mixed
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
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

    private function coerceToObject(mixed $value, Schema $schema, bool $nullableAsType): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $properties = $schema->properties;

        if (null === $properties) {
            return $value;
        }

        $coerced = [];

        foreach ($properties as $name => $propertySchema) {
            if (!isset($value[$name])) {
                continue;
            }

            $coerced[$name] = $this->coerce($value[$name], $propertySchema, true, $nullableAsType);
        }

        return $coerced;
    }

    private function coerceToArray(mixed $value, Schema $schema, bool $nullableAsType): array
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
            $coerced[] = $this->coerce($item, $itemsSchema, true, $nullableAsType);
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
