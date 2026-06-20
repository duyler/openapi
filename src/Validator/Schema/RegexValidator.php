<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use InvalidArgumentException;

use function count;
use function sprintf;
use function str_split;

/**
 * Instance-scoped regex pattern validator and LRU-cached normalizer.
 *
 * Thread-safety: NOT thread-safe. In Swoole coroutines or threaded FrankenPHP,
 * each worker/coroutine must own its own instance (the default in
 * OpenApiValidatorBuilder — one RegexValidator per built document).
 */
final class RegexValidator
{
    private const string DELIMITER_CANDIDATES = '#~!|@%+;';

    private const int DEFAULT_MAX_SIZE = 512;

    /** @var array<string, string> */
    private array $normalizeCache = [];

    /** @var array<string, true> */
    private array $order = [];

    private readonly int $maxSize;

    /**
     * @param int|null $maxSize Maximum normalized patterns retained before LRU eviction.
     *                          Null falls back to DEFAULT_MAX_SIZE.
     *
     * @throws InvalidArgumentException when $maxSize is less than 1
     */
    public function __construct(?int $maxSize = null)
    {
        $resolvedMaxSize = $maxSize ?? self::DEFAULT_MAX_SIZE;

        if (1 > $resolvedMaxSize) {
            throw new InvalidArgumentException(
                sprintf('Max size must be at least 1, got %d', $resolvedMaxSize),
            );
        }

        $this->maxSize = $resolvedMaxSize;
    }

    public function validate(string $pattern, ?string $fieldName = null): string
    {
        $errorContext = new class {
            public string $message = '';
        };

        set_error_handler(function (int $errno, string $errstr) use ($errorContext): bool {
            $errorContext->message = $errstr;

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
                    reason: '' !== $errorContext->message ? $errorContext->message : 'Unknown regex error',
                );
            }
        } finally {
            restore_error_handler();
        }

        return $pattern;
    }

    public function normalize(string $pattern): string
    {
        if (isset($this->normalizeCache[$pattern])) {
            $this->touch($pattern);

            return $this->normalizeCache[$pattern];
        }

        $normalized = $this->doNormalize($pattern);
        $this->normalizeCache[$pattern] = $normalized;
        $this->order[$pattern] = true;

        if (count($this->normalizeCache) > $this->maxSize) {
            $evictedKey = array_key_first($this->order);
            unset($this->order[$evictedKey], $this->normalizeCache[$evictedKey]);
        }

        return $normalized;
    }

    public function clear(): void
    {
        $this->normalizeCache = [];
        $this->order = [];
    }

    private function doNormalize(string $pattern): string
    {
        if ($this->hasDelimiters($pattern)) {
            return $pattern;
        }

        $delimiter = $this->selectDelimiter($pattern);
        $escapedPattern = $this->escapeDelimiter($pattern, $delimiter);

        return $delimiter . $escapedPattern . $delimiter;
    }

    private function hasDelimiters(string $pattern): bool
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

    private function selectDelimiter(string $pattern): string
    {
        foreach (str_split(self::DELIMITER_CANDIDATES) as $candidate) {
            if (false === str_contains($pattern, $candidate)) {
                return $candidate;
            }
        }

        return '/';
    }

    private function escapeDelimiter(string $pattern, string $delimiter): string
    {
        if ('/' === $delimiter) {
            return str_replace('/', '\\/', $pattern);
        }

        return $pattern;
    }

    private function touch(string $key): void
    {
        unset($this->order[$key]);
        $this->order[$key] = true;
    }
}
