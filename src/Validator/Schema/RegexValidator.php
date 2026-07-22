<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use InvalidArgumentException;

use function count;
use function sprintf;
use function str_split;
use function strlen;

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

    /**
     * Hard cap on the byte length of a user-supplied pattern passed to
     * validate(). Patterns beyond this size are rejected before PCRE compiles
     * them, defending against attacker-controlled specifications that ship a
     * 100 KB regex designed to burn CPU during compilation. Constant format
     * patterns inside the library (UUID, email, etc.) are not subject to this
     * limit because they are static and trusted.
     */
    private const int MAX_PATTERN_LENGTH = 1024;

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
    public function __construct(
        ?int $maxSize = null,
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
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
        if (self::MAX_PATTERN_LENGTH < strlen($pattern)) {
            throw new InvalidPatternException(
                pattern: $pattern,
                reason: sprintf('Pattern exceeds maximum length of %d bytes', self::MAX_PATTERN_LENGTH),
            );
        }

        if ('' === $pattern) {
            throw new InvalidPatternException(
                pattern: $pattern,
                reason: 'Empty pattern is not allowed',
            );
        }

        try {
            $testResult = $this->pregExecutor->match($pattern, '');
        } catch (PregRuntimeException $e) {
            throw new InvalidPatternException(
                pattern: $pattern,
                reason: 'PCRE runtime error: ' . $e->getMessage(),
            );
        }

        if (false === $testResult) {
            throw new InvalidPatternException(
                pattern: $pattern,
                reason: 'Unknown regex error',
            );
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
