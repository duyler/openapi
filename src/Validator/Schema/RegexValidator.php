<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;

use function str_starts_with;

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
        if (str_starts_with($pattern, '/')) {
            return $pattern;
        }

        return '/' . $pattern . '/';
    }
}
