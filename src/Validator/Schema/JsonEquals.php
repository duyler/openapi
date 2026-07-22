<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use function abs;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;

final readonly class JsonEquals
{
    /**
     * 2^53 — largest integer that survives a round-trip through IEEE 754
     * double without precision loss. Mixed int+float comparisons above
     * this boundary cannot be decided accurately (the int side loses
     * precision when cast to float), so equality is rejected as false
     * to avoid false-positive matches in uniqueItems / const / enum
     * validation.
     */
    private const int SAFE_INT64_FLOAT_BOUNDARY = 9007199254740992;

    public static function equals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return $a === $b;
        }

        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            if (is_int($a) && is_int($b)) {
                return $a === $b;
            }

            if (is_int($a) && abs($a) > self::SAFE_INT64_FLOAT_BOUNDARY) {
                return false;
            }

            if (is_int($b) && abs($b) > self::SAFE_INT64_FLOAT_BOUNDARY) {
                return false;
            }

            return (float) $a === (float) $b;
        }
        if (is_array($a) && is_array($b)) {
            return self::arraysEqual($a, $b);
        }

        return $a === $b;
    }

    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     */
    private static function arraysEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        if (array_is_list($a) && array_is_list($b)) {
            for ($i = 0, $n = count($a); $i < $n; ++$i) {
                if (!self::equals($a[$i], $b[$i])) {
                    return false;
                }
            }

            return true;
        }

        foreach (array_keys($a) as $key) {
            if (!array_key_exists($key, $b)) {
                return false;
            }

            if (!self::equals($a[$key], $b[$key])) {
                return false;
            }
        }

        return true;
    }
}
