<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\Internal\LruCache;
use Duyler\OpenApi\Validator\PregExecutor;
use InvalidArgumentException;

use function ctype_alnum;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_split;
use function strlen;
use function strrpos;
use function substr;

final readonly class RegexValidator
{
    private const string DELIMITER_CANDIDATES = '#~!|@%+;';

    private const int DEFAULT_MAX_SIZE = 512;

    private const int MAX_PATTERN_LENGTH = 1024;

    /** @var LruCache<string, string> */
    private LruCache $normalizeCache;

    /** @throws InvalidArgumentException */
    public function __construct(
        ?int $maxSize = null,
        private PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        /** @var LruCache<string, string> */
        $this->normalizeCache = new LruCache($maxSize ?? self::DEFAULT_MAX_SIZE);
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
        $cachedPattern = $this->normalizeCache->get($pattern);

        if (null !== $cachedPattern) {
            return $cachedPattern;
        }

        $normalized = $this->doNormalize($pattern);
        $this->normalizeCache->set($pattern, $normalized);

        return $normalized;
    }

    public function clear(): void
    {
        $this->normalizeCache->clear();
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
}
