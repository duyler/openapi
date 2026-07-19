<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util;

use function ord;
use function strlen;

/**
 * Counts UTF-16 code units in a UTF-8 encoded string.
 *
 * Per JSON Schema 2020-12 §6.3.1: "The length of a string is the number
 * of UTF-16 code units." Supplementary characters (U+10000 — U+10FFFF)
 * are encoded as UTF-16 surrogate pairs and count as 2 code units, even
 * though they occupy a single Unicode code point.
 */
final readonly class Utf16
{
    private const int ASCII_BOUNDARY = 0x80;

    private const int TWO_BYTE_LEADING = 0xC0;

    private const int THREE_BYTE_LEADING = 0xE0;

    private const int FOUR_BYTE_LEADING = 0xF0;

    public static function length(string $string): int
    {
        $length = 0;
        $bytes = strlen($string);
        $position = 0;

        while ($position < $bytes) {
            $octet = ord($string[$position]);

            if ($octet < self::ASCII_BOUNDARY) {
                $position += 1;
                $length += 1;
            } elseif ($octet < self::TWO_BYTE_LEADING) {
                $position += 1;
            } elseif ($octet < self::THREE_BYTE_LEADING) {
                $position += 2;
                $length += 1;
            } elseif ($octet < self::FOUR_BYTE_LEADING) {
                $position += 3;
                $length += 1;
            } else {
                $position += 4;
                $length += 2;
            }
        }

        return $length;
    }
}
