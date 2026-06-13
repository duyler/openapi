<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Dto\CoercionContext;

use function is_array;
use function is_string;

final readonly class RequestBodyCoercer extends AbstractCoercer
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
}
