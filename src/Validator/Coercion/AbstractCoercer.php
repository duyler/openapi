<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function abs;
use function fmod;
use function is_array;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_string;
use function sprintf;
use function strlen;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

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

    protected function coerceToBoolean(mixed $value): bool|int|string|float|array|null
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

        /** @var array|null $value */
        return $value;
    }

    protected function coerceToBooleanStrict(mixed $value): bool|int|string|float|array|null
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            return match ($lower) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off' => false,
                default => throw new TypeMismatchError(
                    expected: 'boolean',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                ),
            };
        }

        if (is_int($value)) {
            return 0 !== $value;
        }

        if (is_float($value)) {
            return 0.0 !== $value;
        }

        /** @var array|null $value */
        return $value;
    }

    protected function coerceToInteger(mixed $value): int|string|float|bool|array|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (1 !== preg_match('/^[+-]?\d+$/', $value)) {
                return $value;
            }

            $coerced = (int) $value;

            if ((string) $coerced !== IntegerStringNormalizer::canonicalize($value)) {
                return $value;
            }

            return $coerced;
        }

        if (is_float($value)) {
            if ($this->floatExceedsInt64Range($value)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: sprintf('%F', $value),
                    dataPath: '',
                    schemaPath: '/type',
                    reason: sprintf(
                        'Float value out of integer range [%d, %d]',
                        PHP_INT_MIN,
                        PHP_INT_MAX,
                    ),
                );
            }

            if (0.0 === fmod($value, 1.0)) {
                return (int) $value;
            }

            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        /** @var array|null $value */
        return $value;
    }

    protected function coerceToIntegerStrict(mixed $value): int|string|float|bool|array|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (1 !== preg_match('/^[+-]?\d+$/', $value)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            $coerced = (int) $value;

            if ((string) $coerced !== IntegerStringNormalizer::canonicalize($value)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            return $coerced;
        }

        if (is_float($value)) {
            if (is_infinite($value)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: $value > 0 ? 'INF' : '-INF',
                    dataPath: '',
                    schemaPath: '/type',
                    reason: 'Cannot coerce INF to integer',
                );
            }

            if (is_nan($value)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: 'NAN',
                    dataPath: '',
                    schemaPath: '/type',
                    reason: 'Cannot coerce NaN to integer',
                );
            }

            if (0.0 !== fmod($value, 1.0)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: (string) $value,
                    dataPath: '',
                    schemaPath: '/type',
                    reason: 'Float value has non-zero fractional part',
                );
            }

            if ($this->floatExceedsInt64Range($value)) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: sprintf('%.0f', $value),
                    dataPath: '',
                    schemaPath: '/type',
                    reason: sprintf(
                        'Float value out of integer range [%d, %d]',
                        PHP_INT_MIN,
                        PHP_INT_MAX,
                    ),
                );
            }

            if (abs($value) >= 9007199254740992.0) {
                throw new TypeMismatchError(
                    expected: 'integer',
                    actual: sprintf('%.0f', $value),
                    dataPath: '',
                    schemaPath: '/type',
                    reason: 'Float value exceeds safe integer range (|value| >= 2^53)',
                );
            }

            return (int) $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        /** @var array|null $value */
        return $value;
    }

    protected function coerceToNumber(mixed $value): float|int|string|bool|array|null
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            if (1 !== preg_match('/^[+-]?\d+(\.\d+)?([eE][+-]?\d+)?$/', $value)) {
                return $value;
            }

            return NumberStringNormalizer::castStringToFloatOrFail($value);
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        /** @var array|null $value */
        return $value;
    }

    protected function coerceToNumberStrict(mixed $value): float|int|string|bool|array|null
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            if (1 !== preg_match('/^[+-]?\d+(\.\d+)?([eE][+-]?\d+)?$/', $value)) {
                throw new TypeMismatchError(
                    expected: 'number',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            return NumberStringNormalizer::castStringToFloatOrFail($value);
        }

        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        /** @var array|null $value */
        return $value;
    }

    protected function coerceToString(mixed $value): string|int|float|bool|array|null
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        /** @var array|null $value */
        return $value;
    }

    private function floatExceedsInt64Range(float $value): bool
    {
        $absolute = abs($value);

        if ($absolute < 9.223372036854775E+18) {
            return false;
        }

        if ($absolute > 9.223372036854776E+18) {
            return true;
        }

        $unsignedString = sprintf('%.0f', $absolute);
        $maxString = (string) PHP_INT_MAX;
        $maxLen = strlen($maxString);

        if (strlen($unsignedString) !== $maxLen) {
            return strlen($unsignedString) > $maxLen;
        }

        return $unsignedString > $maxString;
    }
}
