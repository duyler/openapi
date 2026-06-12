<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;

use function str_split;

/**
 * Validates and normalizes regex patterns for JSON Schema draft 2020-12.
 *
 * Per JSON Schema draft 2020-12, the "pattern" keyword requires partial matching
 * (substring match). The pattern matches if the regular expression matches any
 * substring of the input string. Use ^ and $ anchors explicitly for full-string
 * matching.
 */
final class RegexValidator
{
    private const string DELIMITER_CANDIDATES = '#~!|@%+;';

    public static function validate(string $pattern, ?string $fieldName = null): string
    {
        $errorMessage = '';

        set_error_handler(function ($errno, $errstr) use (&$errorMessage): bool {
            $errorMessage = $errstr;
            return true;
        });

        try {
            if ('' === $pattern) {
                throw new InvalidPatternException(
                    pattern: $pattern,
                    reason: 'Empty pattern is not allowed',
                );
            }

            $testResult = preg_match($pattern, '');
            if (false === $testResult) {
                throw new InvalidPatternException(
                    pattern: $pattern,
                    reason: $errorMessage ?: 'Unknown regex error',
                );
            }
        } finally {
            restore_error_handler();
        }

        return $pattern;
    }

    public static function normalize(string $pattern): string
    {
        if (self::hasDelimiters($pattern)) {
            return $pattern;
        }

        $delimiter = self::selectDelimiter($pattern);
        $escapedPattern = self::escapeDelimiter($pattern, $delimiter);

        return $delimiter . $escapedPattern . $delimiter;
    }

    private static function hasDelimiters(string $pattern): bool
    {
        $firstChar = $pattern[0];

        if (ctype_alnum($firstChar) || '\\' === $firstChar || ' ' === $firstChar) {
            return false;
        }

        $lastOccurrence = strrpos($pattern, $firstChar);

        if (false === $lastOccurrence || 0 === $lastOccurrence) {
            return false;
        }

        $modifiers = substr($pattern, $lastOccurrence + 1);

        return '' === $modifiers || 1 === preg_match('/^[imsxADSUXJu]*$/', $modifiers);
    }

    private static function selectDelimiter(string $pattern): string
    {
        foreach (str_split(self::DELIMITER_CANDIDATES) as $candidate) {
            if (false === str_contains($pattern, $candidate)) {
                return $candidate;
            }
        }

        // All candidates present — use '/' as delimiter and escape it inside the pattern
        return '/';
    }

    private static function escapeDelimiter(string $pattern, string $delimiter): string
    {
        if ('/' === $delimiter) {
            return str_replace('/', '\\/', $pattern);
        }

        return $pattern;
    }
}
