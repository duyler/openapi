<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;

/**
 * Formats PHP runtime types using canonical names instead of the legacy
 * labels returned by gettype() (which emits "double", "integer", "NULL").
 *
 * Returned names: int, float, string, bool, array, object, null, resource, unknown.
 */
final readonly class TypeFormatter
{
    public static function format(mixed $value): string
    {
        return match (true) {
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_string($value) => 'string',
            is_bool($value) => 'bool',
            is_array($value) => 'array',
            is_object($value) => 'object',
            null === $value => 'null',
            is_resource($value) => 'resource',
            default => 'unknown',
        };
    }
}
