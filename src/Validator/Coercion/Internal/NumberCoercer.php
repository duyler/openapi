<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion\Internal;

use Duyler\OpenApi\Validator\Coercion\NumberStringNormalizer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final readonly class NumberCoercer
{
    public function coerce(mixed $value): float|int|string|bool|array|null
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

    public function coerceStrict(mixed $value): float|int|string|bool|array|null
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
}
