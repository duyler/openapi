<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use TypeError;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function sprintf;

final readonly class TypeGuarantor
{
    public static function ensureValidType(mixed $value, bool $nullableAsType = true): array|int|string|float|bool|null
    {
        if (null === $value) {
            return null;
        }

        if (is_array($value) || is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        throw new TypeError(sprintf(
            'Value must be array, int, string, float, bool, or null; %s given',
            match (true) {
                is_object($value) => 'object (' . $value::class . ')',
                is_resource($value) => 'resource',
            },
        ));
    }
}
