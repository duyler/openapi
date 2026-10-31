<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\Internal\LruCache;
use Duyler\OpenApi\Validator\PregExecutor;

use function ctype_alnum;
use function preg_match;
use function preg_replace_callback;
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

    private const string UNICODE_MODIFIER = 'u';

    private const string PROPERTY_ESCAPE_PATTERN = '/\\\\[pP]\{([A-Za-z_]+)\}/';

    /**
     * @var array<string, string>
     */
    private const array PCRE_CATEGORY_NAMES = [
        'Letter' => 'L',
        'Uppercase_Letter' => 'Lu',
        'Lowercase_Letter' => 'Ll',
        'Titlecase_Letter' => 'Lt',
        'Modifier_Letter' => 'Lm',
        'Other_Letter' => 'Lo',
        'Cased_Letter' => 'L&',
        'Mark' => 'M',
        'Nonspacing_Mark' => 'Mn',
        'Spacing_Mark' => 'Mc',
        'Enclosing_Mark' => 'Me',
        'Number' => 'N',
        'Decimal_Number' => 'Nd',
        'Letter_Number' => 'Nl',
        'Other_Number' => 'No',
        'Punctuation' => 'P',
        'Connector_Punctuation' => 'Pc',
        'Dash_Punctuation' => 'Pd',
        'Open_Punctuation' => 'Ps',
        'Close_Punctuation' => 'Pe',
        'Initial_Punctuation' => 'Pi',
        'Final_Punctuation' => 'Pf',
        'Other_Punctuation' => 'Po',
        'Symbol' => 'S',
        'Math_Symbol' => 'Sm',
        'Currency_Symbol' => 'Sc',
        'Modifier_Symbol' => 'Sk',
        'Other_Symbol' => 'So',
        'Separator' => 'Z',
        'Space_Separator' => 'Zs',
        'Line_Separator' => 'Zl',
        'Paragraph_Separator' => 'Zp',
        'Other' => 'C',
        'Control' => 'Cc',
        'Format' => 'Cf',
        'Surrogate' => 'Cs',
        'Private_Use' => 'Co',
        'Unassigned' => 'Cn',
    ];

    private const int DEFAULT_MAX_SIZE = 512;

    private const int MAX_PATTERN_LENGTH = 1024;

    /** @var LruCache<string, string> */
    private LruCache $normalizeCache;

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
        $translated = $this->translateUnicodePropertyEscapes($pattern);

        if ($this->hasDelimiters($translated)) {
            return $this->withUnicodeModifier($translated);
        }

        $delimiter = $this->selectDelimiter($translated);
        $escapedPattern = $this->escapeDelimiter($translated, $delimiter);

        return $delimiter . $escapedPattern . $delimiter . self::UNICODE_MODIFIER;
    }

    private function translateUnicodePropertyEscapes(string $pattern): string
    {
        return (string) preg_replace_callback(
            self::PROPERTY_ESCAPE_PATTERN,
            static function (array $matches): string {
                $escape = $matches[0];
                $name = $matches[1] ?? '';
                $replacement = self::PCRE_CATEGORY_NAMES[$name] ?? null;

                return null === $replacement ? $escape : substr($escape, 0, -strlen($name) - 1) . $replacement . '}';
            },
            $pattern,
        );
    }

    private function withUnicodeModifier(string $pattern): string
    {
        if ($this->hasUnicodeModifier($pattern)) {
            return $pattern;
        }

        return $pattern . self::UNICODE_MODIFIER;
    }

    private function hasUnicodeModifier(string $pattern): bool
    {
        $lastOccurrence = strrpos($pattern, $pattern[0]);

        if (false === $lastOccurrence || 0 === $lastOccurrence) {
            return false;
        }

        return str_contains(substr($pattern, $lastOccurrence + 1), self::UNICODE_MODIFIER);
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
