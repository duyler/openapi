<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

readonly class TypeGuarantor
{
    public static function ensureValidType(mixed $value, bool $nullableAsType = true): array|int|string|float|bool|null
    {
        if (is_array($value)) {
            return $value;
        }

        if (null === $value && $nullableAsType) {
            return $value;
        }

        if (is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return (string) $value;
    }
}
