<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Override;

use function is_array;
use function is_string;
use function array_key_exists;

final readonly class ResponseTypeCoercer extends AbstractCoercer
{
    public function coerce(mixed $value, CoercionContext $context): array|int|string|float|bool|null
    {
        if (false === $context->enabled || null === $context->schema) {
            /** @var array|int|string|float|bool|null $value */
            return $value;
        }

        if (null === $value && $context->schema->nullable && $context->nullableAsType) {
            return $value;
        }

        $type = $context->schema->type;

        if (null === $type) {
            /** @var array|int|string|float|bool|null $value */
            return $value;
        }

        if (is_array($type)) {
            return $this->coerceUnionType($value, $type, $context);
        }

        return $this->coerceToType($value, $type, $context);
    }

    #[Override]
    protected function coerceToString(mixed $value): string|int|float|bool|array|null
    {
        /** @var string|int|float|bool|array|null $value */
        return $value;
    }

    private function coerceUnionType(mixed $value, array $types, CoercionContext $context): array|int|string|float|bool|null
    {
        foreach ($types as $type) {
            if (false === is_string($type) || 'null' === $type) {
                continue;
            }

            $coerced = $this->coerceToType($value, $type, $context);

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        /** @var array|int|string|float|bool|null $value */
        return $value;
    }

    private function coerceToType(mixed $value, string $type, CoercionContext $context): array|int|string|float|bool|null
    {
        /** @var array|int|string|float|bool|null $coerced */
        $coerced = match ($type) {
            'string' => $this->coerceToString($value),
            'integer' => $context->strict ? $this->coerceToIntegerStrict($value) : $this->coerceToInteger($value),
            'number' => $context->strict ? $this->coerceToNumberStrict($value) : $this->coerceToNumber($value),
            'boolean' => $context->strict ? $this->coerceToBooleanStrict($value) : $this->coerceToBoolean($value),
            'object' => $this->coerceToObject($value, $context),
            'array' => $this->coerceToArray($value, $context),
            default => $value,
        };

        return $coerced;
    }

    private function coerceToObject(mixed $value, CoercionContext $context): array|int|string|float|bool|null
    {
        if (false === is_array($value)) {
            /** @var array|int|string|float|bool|null $value */
            return $value;
        }

        $properties = $context->schema->properties ?? null;

        if (null === $properties) {
            return $value;
        }

        /** @var array<string, mixed> $coerced */
        $coerced = $value;

        foreach ($properties as $name => $propertySchema) {
            if (false === array_key_exists($name, $value)) {
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

    private function coerceToArray(mixed $value, CoercionContext $context): array|int|string|float|bool|null
    {
        if (false === is_array($value)) {
            /** @var array|int|string|float|bool|null $value */
            return $value;
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
