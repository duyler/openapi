<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion\Internal;

use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function strtolower;

/** @internal */
final readonly class BooleanCoercer
{
    public function coerce(mixed $value): bool|int|string|float|array|null
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

    public function coerceStrict(mixed $value): bool|int|string|float|array|null
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
}
