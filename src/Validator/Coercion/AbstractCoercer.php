<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_string;

abstract readonly class AbstractCoercer
{
    protected function isValidType(mixed $value, string $type): bool
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

    protected function coerceToBoolean(mixed $value): mixed
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

    protected function coerceToInteger(mixed $value, bool $strict = false): mixed
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
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

    protected function coerceToNumber(mixed $value, bool $strict = false): mixed
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

    protected function coerceToString(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $value;
    }
}
