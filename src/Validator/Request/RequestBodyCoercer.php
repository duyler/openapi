<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_array;
use function is_string;

final readonly class RequestBodyCoercer extends AbstractCoercer
{
    public function coerce(mixed $value, CoercionContext $context): array|int|string|float|bool|null
    {
        if (false === $context->enabled || null === $context->schema) {
            /** @var array|int|string|float|bool|null $value */
            return $value;
        }

        return $this->coerceInternal(
            $value,
            $context->schema,
            $context->strict,
            $context->nullableAsType,
        );
    }

    private function coerceInternal(mixed $value, Schema $schema, bool $strict, bool $nullableAsType): array|int|string|float|bool|null
    {
        if (null === $value && $schema->nullable && $nullableAsType) {
            return $value;
        }

        $type = $schema->type;

        if (null === $type) {
            /** @var array|int|string|float|bool|null $value */
            return $value;
        }

        if (is_array($type)) {
            return $this->coerceUnionType($value, $type, $schema, $strict, $nullableAsType);
        }

        return $this->coerceToType($value, $type, $schema, $strict, $nullableAsType);
    }

    private function coerceUnionType(mixed $value, array $types, Schema $schema, bool $strict, bool $nullableAsType): array|int|string|float|bool|null
    {
        foreach ($types as $type) {
            if (false === is_string($type) || 'null' === $type) {
                continue;
            }

            try {
                /** @var array|int|string|float|bool|null $coerced */
                $coerced = $this->coerceToType($value, $type, $schema, $strict, $nullableAsType);
            } catch (TypeMismatchError) {
                continue;
            }

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        /** @var array|int|string|float|bool|null $value */
        return $value;
    }

    private function coerceToType(mixed $value, string $type, Schema $schema, bool $strict, bool $nullableAsType): array|int|string|float|bool|null
    {
        /** @var array|int|string|float|bool|null $coerced */
        $coerced = match ($type) {
            'string' => $this->coerceToString($value),
            'integer' => $strict ? $this->coerceToIntegerStrict($value) : $this->coerceToInteger($value),
            'number' => $strict ? $this->coerceToNumberStrict($value) : $this->coerceToNumber($value),
            'boolean' => $strict ? $this->coerceToBooleanStrict($value) : $this->coerceToBoolean($value),
            'object' => $this->coerceToObject($value, $schema, $strict, $nullableAsType),
            'array' => $this->coerceToArray($value, $schema, $strict, $nullableAsType),
            default => $value,
        };

        return $coerced;
    }

    private function coerceToObject(mixed $value, Schema $schema, bool $strict, bool $nullableAsType): array|int|string|float|bool|null
    {
        /** @var array|int|string|float|bool|null */
        return $this->coerceDeclaredProperties($value, $schema, $this->recursion($strict, $nullableAsType));
    }

    private function coerceToArray(mixed $value, Schema $schema, bool $strict, bool $nullableAsType): array|int|string|float|bool|null
    {
        /** @var array|int|string|float|bool|null */
        return $this->coerceDeclaredItems($value, $schema, $this->recursion($strict, $nullableAsType));
    }

    /**
     * @return callable(mixed, Schema): (array|int|string|float|bool|null)
     */
    private function recursion(bool $strict, bool $nullableAsType): callable
    {
        return fn(mixed $value, Schema $schema): array|int|string|float|bool|null
            => $this->coerceInternal($value, $schema, $strict, $nullableAsType);
    }
}
