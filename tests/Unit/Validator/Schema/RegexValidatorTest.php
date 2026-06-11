<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegexValidatorTest extends TestCase
{
    #[Test]
    public function valid_pattern_passes(): void
    {
        $pattern = '/^test$/';
        $result = RegexValidator::validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function valid_pattern_without_delimiters_passes(): void
    {
        $pattern = '^test$';
        $result = RegexValidator::validate('/' . $pattern . '/');
        self::assertSame('/' . $pattern . '/', $result);
    }

    #[Test]
    public function invalid_pattern_throws_error(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "[invalid":');
        RegexValidator::validate('[invalid');
    }

    #[Test]
    public function invalid_pattern_with_unclosed_bracket_throws_error(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "[0-9":');
        RegexValidator::validate('[0-9');
    }

    #[Test]
    public function pattern_without_delimiters_normalized(): void
    {
        $result = RegexValidator::normalize('^test$');
        self::assertSame('#^test$#', $result);
    }

    #[Test]
    public function pattern_with_delimiters_not_normalized(): void
    {
        $pattern = '/^test$/';
        $result = RegexValidator::normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function empty_pattern_valid(): void
    {
        $pattern = '//';
        $result = RegexValidator::validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function complex_pattern_valid(): void
    {
        $pattern = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
        $result = RegexValidator::validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function pattern_with_modifiers_valid(): void
    {
        $pattern = '/test/i';
        $result = RegexValidator::validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function pattern_with_field_name_included_in_exception(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "[invalid": preg_match(): No ending matching delimiter \']\' found');
        RegexValidator::validate('[invalid', 'test field');
    }

    #[Test]
    public function throw_error_for_empty_pattern(): void
    {
        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "": Empty pattern is not allowed');
        RegexValidator::validate('');
    }

    #[Test]
    public function normalize_pattern_with_forward_slash(): void
    {
        $result = RegexValidator::normalize('path/to/resource');

        self::assertSame('#path/to/resource#', $result);

        self::assertSame(1, preg_match($result, 'path/to/resource'));
        self::assertSame(0, preg_match($result, 'other/value'));
    }

    #[Test]
    public function normalize_pattern_with_tilde(): void
    {
        $result = RegexValidator::normalize('hello~world');

        self::assertSame('#hello~world#', $result);

        self::assertSame(1, preg_match($result, 'hello~world'));
    }

    #[Test]
    public function normalize_pattern_with_hash(): void
    {
        $result = RegexValidator::normalize('section#anchor');

        self::assertSame('~section#anchor~', $result);

        self::assertSame(1, preg_match($result, 'section#anchor'));
    }

    #[Test]
    public function normalize_pattern_with_slash_tilde_and_hash(): void
    {
        $result = RegexValidator::normalize('path/~value#frag');

        self::assertSame('!path/~value#frag!', $result);

        self::assertSame(1, preg_match($result, 'path/~value#frag'));
    }

    #[Test]
    public function validate_pattern_with_slash_delimiters_and_forward_slash_inside(): void
    {
        $pattern = '/path\/to\/resource/';
        $result = RegexValidator::validate($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_simple_path_pattern_works_with_preg_match(): void
    {
        $normalized = RegexValidator::normalize('path/to/resource');

        self::assertSame(1, preg_match($normalized, '/some/path/to/resource/here'));
    }

    #[Test]
    public function normalize_already_delimited_pattern_unchanged(): void
    {
        $pattern = '/^test$/';
        $result = RegexValidator::normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_hash_delimited_pattern_unchanged(): void
    {
        $pattern = '#^test$#';
        $result = RegexValidator::normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_tilde_delimited_pattern_unchanged(): void
    {
        $pattern = '~^test$~';
        $result = RegexValidator::normalize($pattern);
        self::assertSame($pattern, $result);
    }

    #[Test]
    public function normalize_pattern_with_all_delimiter_candidates_and_slash(): void
    {
        // Pattern contains ALL 8 delimiter candidates (#~!|@%+;) AND /
        $result = RegexValidator::normalize('#~!|@%+;path/to/resource');

        // Should fallback to '/' as delimiter and escape slashes inside
        self::assertSame('/#~!|@%+;path\/to\/resource/', $result);

        // Must be a valid regex that actually works
        self::assertSame(1, preg_match($result, '#~!|@%+;path/to/resource'));
        self::assertSame(0, preg_match($result, 'other/value'));
    }

    #[Test]
    public function normalize_pattern_with_pipe_at_and_slash(): void
    {
        $result = RegexValidator::normalize('path|value@here/now');

        self::assertSame('#path|value@here/now#', $result);

        self::assertSame(1, preg_match($result, 'path|value@here/now'));
    }

    #[Test]
    public function normalize_pattern_with_all_old_candidates_plus_slash(): void
    {
        // Old candidates were #~!| — with / all present, ! is still available
        $result = RegexValidator::normalize('#~|path/to/resource');

        self::assertSame('!#~|path/to/resource!', $result);

        self::assertSame(1, preg_match($result, '#~|path/to/resource'));
    }
}
