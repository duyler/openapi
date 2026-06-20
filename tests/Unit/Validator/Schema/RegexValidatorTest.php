<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function preg_match;
use function sprintf;
use function count;

final class RegexValidatorTest extends TestCase
{
    private RegexValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RegexValidator();
    }

    protected function tearDown(): void
    {
        unset($this->validator);
    }

    #[Test]
    public function valid_pattern_passes(): void
    {
        $pattern = '/^test$/';
        $result = $this->validator->validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function valid_pattern_without_delimiters_passes(): void
    {
        $pattern = '^test$';
        $result = $this->validator->validate('/' . $pattern . '/');
        self::assertSame('/' . $pattern . '/', $result);
    }

    #[Test]
    public function invalid_pattern_throws_error(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "[invalid":');
        $this->validator->validate('[invalid');
    }

    #[Test]
    public function invalid_pattern_with_unclosed_bracket_throws_error(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "[0-9":');
        $this->validator->validate('[0-9');
    }

    #[Test]
    public function pattern_without_delimiters_normalized(): void
    {
        $result = $this->validator->normalize('^test$');
        self::assertSame('#^test$#', $result);
    }

    #[Test]
    public function pattern_with_delimiters_not_normalized(): void
    {
        $pattern = '/^test$/';
        $result = $this->validator->normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function empty_pattern_valid(): void
    {
        $pattern = '//';
        $result = $this->validator->validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function complex_pattern_valid(): void
    {
        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        $result = $this->validator->validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function pattern_with_modifiers_valid(): void
    {
        $pattern = '/test/i';
        $result = $this->validator->validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function pattern_with_field_name_included_in_exception(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "[invalid": preg_match(): No ending matching delimiter \']\' found');
        $this->validator->validate('[invalid', 'test field');
    }

    #[Test]
    public function throw_error_for_empty_pattern(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "": Empty pattern is not allowed');
        $this->validator->validate('');
    }

    #[Test]
    public function normalize_pattern_with_forward_slash(): void
    {
        $result = $this->validator->normalize('path/to/resource');

        self::assertSame('#path/to/resource#', $result);

        self::assertSame(1, preg_match($result, 'path/to/resource'));
        self::assertSame(0, preg_match($result, 'other/value'));
    }

    #[Test]
    public function normalize_pattern_with_tilde(): void
    {
        $result = $this->validator->normalize('hello~world');

        self::assertSame('#hello~world#', $result);

        self::assertSame(1, preg_match($result, 'hello~world'));
    }

    #[Test]
    public function normalize_pattern_with_hash(): void
    {
        $result = $this->validator->normalize('section#anchor');

        self::assertSame('~section#anchor~', $result);

        self::assertSame(1, preg_match($result, 'section#anchor'));
    }

    #[Test]
    public function normalize_pattern_with_slash_tilde_and_hash(): void
    {
        $result = $this->validator->normalize('path/~value#frag');

        self::assertSame('!path/~value#frag!', $result);

        self::assertSame(1, preg_match($result, 'path/~value#frag'));
    }

    #[Test]
    public function validate_pattern_with_slash_delimiters_and_forward_slash_inside(): void
    {
        $pattern = '/path\/to\/resource/';
        $result = $this->validator->validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_simple_path_pattern_works_with_preg_match(): void
    {
        $normalized = $this->validator->normalize('path/to/resource');

        self::assertSame(1, preg_match($normalized, '/some/path/to/resource/here'));
    }

    #[Test]
    public function normalize_already_delimited_pattern_unchanged(): void
    {
        $pattern = '/^test$/';
        $result = $this->validator->normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_hash_delimited_pattern_unchanged(): void
    {
        $pattern = '#^test$#';
        $result = $this->validator->normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_tilde_delimited_pattern_unchanged(): void
    {
        $pattern = '~^test$~';
        $result = $this->validator->normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_pattern_with_all_delimiter_candidates_and_slash(): void
    {
        // Pattern contains ALL 8 delimiter candidates (#~!|@%+;) AND /
        $result = $this->validator->normalize('#~!|@%+;path/to/resource');

        // Should fallback to '/' as delimiter and escape slashes inside
        self::assertSame('/#~!|@%+;path\/to\/resource/', $result);

        // Must be a valid regex that actually works
        self::assertSame(1, preg_match($result, '#~!|@%+;path/to/resource'));
        self::assertSame(0, preg_match($result, 'other/value'));
    }

    #[Test]
    public function normalize_pattern_with_pipe_at_and_slash(): void
    {
        $result = $this->validator->normalize('path|value@here/now');

        self::assertSame('#path|value@here/now#', $result);

        self::assertSame(1, preg_match($result, 'path|value@here/now'));
    }

    #[Test]
    public function normalize_pattern_with_all_old_candidates_plus_slash(): void
    {
        // Old candidates were #~!| — with / all present, ! is still available
        $result = $this->validator->normalize('#~|path/to/resource');

        self::assertSame('!#~|path/to/resource!', $result);

        self::assertSame(1, preg_match($result, '#~|path/to/resource'));
    }

    #[Test]
    public function normalize_returns_same_result_on_repeated_call(): void
    {
        $first = $this->validator->normalize('^cached$');
        $second = $this->validator->normalize('^cached$');

        self::assertSame($first, $second);
    }

    #[Test]
    public function clear_resets_normalize_cache(): void
    {
        $before = $this->validator->normalize('^cached$');

        $this->validator->clear();

        $after = $this->validator->normalize('^cached$');

        self::assertSame($before, $after);
        self::assertSame(1, $this->normalizeCacheSize());
    }

    #[Test]
    public function normalize_cache_does_not_affect_different_patterns(): void
    {
        $alpha = $this->validator->normalize('^alpha$');
        $beta = $this->validator->normalize('^beta$');

        self::assertNotSame($alpha, $beta);

        self::assertSame(1, preg_match($alpha, 'alpha'));
        self::assertSame(0, preg_match($alpha, 'beta'));

        self::assertSame(1, preg_match($beta, 'beta'));
        self::assertSame(0, preg_match($beta, 'alpha'));
    }

    #[Test]
    public function constructor_rejects_max_size_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max size must be at least 1, got 0');

        new RegexValidator(0);
    }

    #[Test]
    public function constructor_accepts_max_size_of_one(): void
    {
        $validator = new RegexValidator(maxSize: 1);

        $validator->normalize('^a$');
        $validator->normalize('^b$');

        // With maxSize=1, the second insert evicts the first. Validate still works.
        self::assertSame('#^b$#', $validator->normalize('^b$'));
    }

    #[Test]
    public function normalize_pattern_starting_with_backslash_wraps_with_delimiter(): void
    {
        // Backslash-first pattern has no delimiters; must be wrapped.
        $result = $this->validator->normalize('\d+');

        self::assertSame('#\d+#', $result);
        self::assertSame(1, preg_match($result, '123'));
    }

    #[Test]
    public function normalize_pattern_starting_with_space_wraps_with_delimiter(): void
    {
        $result = $this->validator->normalize(' test');

        self::assertSame('# test#', $result);
        self::assertSame(1, preg_match($result, ' test'));
    }

    #[Test]
    public function normalize_pattern_with_single_occurrence_first_char_wraps_with_delimiter(): void
    {
        // '#' appears only once (at position 0) — no closing delimiter.
        $result = $this->validator->normalize('#abc');

        self::assertNotSame('#abc', $result);
        self::assertSame(1, preg_match($result, '#abc'));
    }

    #[Test]
    public function normalize_delimited_pattern_with_valid_modifier_keeps_delimiters(): void
    {
        $result = $this->validator->normalize('#test#i');

        self::assertSame('#test#i', $result);
    }

    #[Test]
    public function normalize_delimited_pattern_with_empty_modifier_keeps_delimiters(): void
    {
        $result = $this->validator->normalize('#test#');

        self::assertSame('#test#', $result);
    }

    #[Test]
    public function normalize_pattern_with_invalid_modifier_chars_wraps_with_delimiter(): void
    {
        // '#test#e' — 'e' is not a valid PCRE modifier (not in [imsxADSUXJu]),
        // so hasDelimiters returns false and the whole string is re-wrapped.
        $result = $this->validator->normalize('#test#e');

        self::assertNotSame('#test#e', $result);
    }

    #[Test]
    public function constructor_rejects_negative_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RegexValidator(-3);
    }

    #[Test]
    public function eviction_keeps_normalize_cache_at_max_size_after_overflow(): void
    {
        $validator = new RegexValidator(maxSize: 3);

        $validator->normalize('p1');
        $validator->normalize('p2');
        $validator->normalize('p3');
        $validator->normalize('p4');

        self::assertSame(3, $this->normalizeCacheSize($validator));
    }

    #[Test]
    public function eviction_removes_least_recently_used_pattern(): void
    {
        $validator = new RegexValidator(maxSize: 3);

        $validator->normalize('p1');
        $validator->normalize('p2');
        $validator->normalize('p3');
        // Touch 'p1' so 'p2' becomes the LRU victim.
        $validator->normalize('p1');
        $validator->normalize('p4');

        $keys = $this->normalizeCacheKeys($validator);

        self::assertContains('p1', $keys);
        self::assertContains('p3', $keys);
        self::assertContains('p4', $keys);
        self::assertNotContains('p2', $keys);
    }

    #[Test]
    public function default_capacity_holds_512_entries_and_evicts_on_513(): void
    {
        for ($i = 0; $i < 512; ++$i) {
            $this->validator->normalize(sprintf('pat%d', $i));
        }

        self::assertSame(512, $this->normalizeCacheSize());

        $this->validator->normalize('overflow');

        self::assertSame(512, $this->normalizeCacheSize());
        self::assertNotContains('pat0', $this->normalizeCacheKeys());
        self::assertContains('overflow', $this->normalizeCacheKeys());
    }

    #[Test]
    public function touch_promotes_pattern_to_most_recently_used(): void
    {
        $validator = new RegexValidator(maxSize: 2);

        $validator->normalize('p1');
        $validator->normalize('p2');
        // Access 'p1' — 'p2' is now LRU.
        $validator->normalize('p1');
        $validator->normalize('p3');

        $keys = $this->normalizeCacheKeys($validator);

        self::assertContains('p1', $keys);
        self::assertContains('p3', $keys);
        self::assertNotContains('p2', $keys);
    }

    #[Test]
    public function clear_empties_normalize_cache_and_allows_reuse(): void
    {
        $this->validator->normalize('^a$');
        $this->validator->normalize('^b$');

        self::assertSame(2, $this->normalizeCacheSize());

        $this->validator->clear();

        self::assertSame(0, $this->normalizeCacheSize());

        $result = $this->validator->normalize('^a$');

        self::assertSame('#^a$#', $result);
        self::assertSame(1, $this->normalizeCacheSize());
    }

    #[Test]
    public function validate_does_not_populate_normalize_cache(): void
    {
        $this->validator->validate('/^test$/');

        self::assertSame(0, $this->normalizeCacheSize());
    }

    /**
     * @return int<0, max>
     */
    private function normalizeCacheSize(?RegexValidator $validator = null): int
    {
        return count($this->normalizeCacheArray($validator ?? $this->validator));
    }

    /**
     * @return list<string>
     */
    private function normalizeCacheKeys(?RegexValidator $validator = null): array
    {
        /** @var array<string, string> $data */
        $data = $this->normalizeCacheArray($validator ?? $this->validator);

        return array_keys($data);
    }

    /**
     * @return array<string, string>
     */
    private function normalizeCacheArray(RegexValidator $validator): array
    {
        $reflection = new ReflectionClass($validator);
        $property = $reflection->getProperty('normalizeCache');

        /** @var array<string, string> $value */
        $value = $property->getValue($validator);

        return $value;
    }
}
