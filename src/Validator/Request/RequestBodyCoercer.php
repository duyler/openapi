<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;

final readonly class RequestBodyCoercer
{
    public function coerce(mixed $value, CoercionContext $context): mixed
    {
        if (false === $context->enabled || null === $context->schema) {
            return $value;
        }

        if (null === $value && $context->schema->nullable && $context->nullableAsType) {
            return $value;
        }

        $type = $context->schema->type;

        if (null === $type) {
            return $value;
        }

        if (is_array($type)) {
            return $this->coerceUnionType($value, $type, $context);
        }

        return $this->coerceToType($value, $type, $context);
    }

    private function coerceUnionType(mixed $value, array $types, CoercionContext $context): mixed
    {
        foreach ($types as $type) {
            if (!is_string($type) || 'null' === $type) {
                continue;
            }

            $childContext = new CoercionContext(
                schema: $context->schema,
                enabled: true,
                strict: $context->strict,
                nullableAsType: $context->nullableAsType,
            );

            /** @var array|int|string|float|bool|null $coerced */
            $coerced = $this->coerceToType($value, $type, $childContext);

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        return $value;
    }

    private function coerceToType(mixed $value, string $type, CoercionContext $context): mixed
    {
        return match ($type) {
            'string' => $this->coerceToString($value),
            'integer' => $this->coerceToInteger($value, $context->strict),
            'number' => $this->coerceToNumber($value, $context->strict),
            'boolean' => $this->coerceToBoolean($value),
            'object' => $this->coerceToObject($value, $context),
            'array' => $this->coerceToArray($value, $context),
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
            return 0 !== $value;
        }

        if (is_float($value)) {
            return 0.0 !== $value;
        }

        return $value;
    }

    private function coerceToObject(mixed $value, CoercionContext $context): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $properties = $context->schema->properties ?? null;

        if (null === $properties) {
            return $value;
        }

        /** @var array<string, mixed> $coerced */
        $coerced = $value;

        foreach ($properties as $name => $propertySchema) {
            if (!isset($value[$name])) {
                continue;
            }

            $childContext = new CoercionContext(
                schema: $propertySchema,
                enabled: true,
                strict: $context->strict,
                nullableAsType: $context->nullableAsType,
            );

            $coerced[$name] = $this->coerce($value[$name], $childContext);
        }

        return $coerced;
    }

    private function coerceToArray(mixed $value, CoercionContext $context): array
    {
        if (!is_array($value)) {
            return [];
        }

        $itemsSchema = $context->schema->items ?? null;

        if (null === $itemsSchema) {
            return $value;
        }

        $childContext = new CoercionContext(
            schema: $itemsSchema,
            enabled: true,
            strict: $context->strict,
            nullableAsType: $context->nullableAsType,
        );

        $coerced = [];

        foreach ($value as $item) {
            $coerced[] = $this->coerce($item, $childContext);
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
