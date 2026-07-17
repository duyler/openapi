<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util;

use function dechex;
use function mb_substr;
use function ord;
use function preg_replace_callback;
use function sprintf;
use function str_pad;
use function strlen;

use const STR_PAD_LEFT;

/**
 * Sanitizes attacker-controlled strings before they enter PSR-3 log context.
 * Truncates to a bounded length and escapes control characters so that a
 * plaintext logger (e.g. Monolog StreamHandler) cannot be injected with
 * fake log lines via newline or other control bytes.
 */
final readonly class LogContextSanitizer
{
    private const int DEFAULT_MAX_LENGTH = 256;

    public static function truncate(string $value, int $max = self::DEFAULT_MAX_LENGTH): string
    {
        $length = strlen($value);

        if ($length <= $max) {
            return self::escapeControlChars($value);
        }

        $truncated = mb_substr($value, 0, $max, 'UTF-8');

        return self::escapeControlChars($truncated)
            . sprintf('...(truncated, %d bytes total)', $length);
    }

    private static function escapeControlChars(string $value): string
    {
        $escaped = preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            static function (array $m): string {
                return '\x' . str_pad(dechex(ord($m[0])), 2, '0', STR_PAD_LEFT);
            },
            $value,
        );

        return $escaped ?? $value;
    }
}
