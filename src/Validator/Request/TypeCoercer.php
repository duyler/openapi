<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;
use function is_array;
use function is_object;
use function get_object_vars;

readonly class TypeCoercer
{
    /**
     * @return array<array-key, mixed>|int|string|float|bool
     */
    public function coerce(mixed $value, Parameter $param, bool $enabled, bool $strict = false): array|int|string|float|bool
    {
        if (null === $value) {
            $value = '';
        }

        if (false === $enabled || null === $param->schema) {
            return $this->normalizeValue($value);
        }

        $schema = $param->schema;

        if (null === $schema->type) {
            return $this->normalizeValue($value);
        }

        if (is_array($schema->type)) {
            return $this->coerceUnionType($value, $schema->type, $strict);
        }

        return $this->coerceToType($value, $schema->type, $strict);
    }

    /**
     * @param array<int, string> $types
     * @return array<array-key, mixed>|int|string|float|bool
     */
    private function coerceUnionType(mixed $value, array $types, bool $strict): array|int|string|float|bool
    {
        foreach ($types as $type) {
            if ('null' === $type) {
                continue;
            }

            $coerced = $this->coerceToType($value, $type, $strict);

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        return $this->normalizeValue($value);
    }

    /**
     * @return array<array-key, mixed>|int|string|float|bool
     */
    private function coerceToType(mixed $value, string $type, bool $strict): array|int|string|float|bool
    {
        if (is_string($value)) {
            return match ($type) {
                'integer' => $this->coerceToInteger($value, $strict),
                'number' => $this->coerceToNumber($value, $strict),
                'boolean' => $this->coerceToBoolean($value),
                default => $value,
            };
        }

        return $this->normalizeValue($value);
    }

    /**
     * @return array<array-key, mixed>|int|string|float|bool
     */
    private function normalizeValue(mixed $value): array|int|string|float|bool
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return (string) $value;
    }

    private function coerceToInteger(string $value, bool $strict): int
    {
        if ($strict && (!is_numeric($value) || (string) (int) $value !== $value)) {
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

    private function coerceToNumber(string $value, bool $strict): float
    {
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

    private function coerceToBoolean(string $value): bool
    {
        $lower = strtolower($value);

        return match ($lower) {
            'true', '1', 'yes', 'on' => true,
            'false', '0', 'no', 'off' => false,
            default => (bool) $value,
        };
    }

    private function isValidType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_float($value) || is_int($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'null' => null === $value,
            'object' => is_object($value),
            default => true,
        };
    }
}
