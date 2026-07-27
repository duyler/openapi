<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion\Internal;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/** @internal */
final readonly class StringCoercer
{
    public function isValidType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'number' => is_float($value) || is_int($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'null' => null === $value,
            'object', 'array' => is_array($value),
            default => true,
        };
    }

    public function coerce(mixed $value): string|int|float|bool|array|null
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
}
