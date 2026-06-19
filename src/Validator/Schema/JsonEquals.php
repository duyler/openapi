<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use function is_float;
use function is_int;

final readonly class JsonEquals
{
    public static function equals(mixed $a, mixed $b): bool
    {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return (float) $a === (float) $b;
        }

        return $a === $b;
    }
}
