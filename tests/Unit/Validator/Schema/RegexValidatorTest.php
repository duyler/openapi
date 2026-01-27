<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Tests\Unit\Validator\Schema;

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
        self::assertSame('/^test$/', $result);
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
}
