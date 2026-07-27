<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion\Internal;

use Duyler\OpenApi\Validator\Coercion\IntegerStringNormalizer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function abs;
use function fmod;
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

/** @internal */
final readonly class IntegerCoercer
{
    private const float SAFE_INT64_FLOAT_BOUNDARY = 9007199254740992.0;

    private const float INT64_MIN_FLOAT = 9.223372036854775E+18;

    private const float INT64_MAX_FLOAT = 9.223372036854776E+18;

    public function coerce(mixed $value): int|string|float|bool|array|null
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
            if ($this->exceedsInt64Range($value)) {
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

    public function coerceStrict(mixed $value): int|string|float|bool|array|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->coerceStringStrict($value);
        }

        if (is_float($value)) {
            return $this->coerceFloatStrict($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        /** @var array|null $value */
        return $value;
    }

    private function coerceStringStrict(string $value): int
    {
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

    private function coerceFloatStrict(float $value): int
    {
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

        if ($this->exceedsInt64Range($value)) {
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

        if (abs($value) >= self::SAFE_INT64_FLOAT_BOUNDARY) {
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

    private function exceedsInt64Range(float $value): bool
    {
        $absolute = abs($value);

        if ($absolute < self::INT64_MIN_FLOAT) {
            return false;
        }

        if ($absolute > self::INT64_MAX_FLOAT) {
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
