<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion\Internal;

use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Response\Enum\BooleanCoercionValue;

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
            $coerced = $this->coerceStringStrict($value);

            if (null !== $coerced) {
                return $coerced;
            }

            return (bool) $value;
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
            $coerced = $this->coerceStringStrict($value);

            if (null === $coerced) {
                throw new TypeMismatchError(
                    expected: 'boolean',
                    actual: $value,
                    dataPath: '',
                    schemaPath: '/type',
                );
            }

            return $coerced;
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

    public function coerceStringStrict(string $value): ?bool
    {
        return BooleanCoercionValue::tryFrom(strtolower($value))?->isTruthy();
    }
}
