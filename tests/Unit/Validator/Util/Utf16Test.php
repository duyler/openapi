<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util;

use Duyler\OpenApi\Validator\Util\Utf16;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Validates UTF-16 code-unit counting per JSON Schema 2020-12 §6.3.1.
 *
 * Supplementary plane characters (U+10000 — U+10FFFF) are represented
 * as UTF-16 surrogate pairs and must count as 2 units — not 1, which
 * is what naive mb_strlen($s, 'UTF-8') would return.
 */
#[CoversClass(Utf16::class)]
final class Utf16Test extends TestCase
{
    #[Test]
    public function ascii_string_length(): void
    {
        $length = Utf16::length('hello');

        self::assertSame(5, $length);
    }

    #[Test]
    public function bmp_string_length(): void
    {
        $length = Utf16::length('café');

        self::assertSame(4, $length);
    }

    #[Test]
    public function cjk_bmp_string_length(): void
    {
        $length = Utf16::length('日本語');

        self::assertSame(3, $length);
    }

    #[Test]
    public function supplementary_string_length(): void
    {
        $length = Utf16::length('😀');

        self::assertSame(2, $length);
    }

    #[Test]
    public function mixed_bmp_supplementary_length(): void
    {
        $length = Utf16::length('a😀b');

        self::assertSame(4, $length);
    }

    #[Test]
    public function cyrillic_bmp_length(): void
    {
        $length = Utf16::length('Привет');

        self::assertSame(6, $length);
    }

    #[Test]
    public function supplementary_cjk_length(): void
    {
        // U+20BB7 (CJK Extension C) — encoded as 4 bytes in UTF-8,
        // 2 UTF-16 code units via surrogate pair.
        $length = Utf16::length("\xF0\xA0\xAE\xB7");

        self::assertSame(2, $length);
    }

    #[Test]
    public function zwj_emoji_sequence_length(): void
    {
        // Family with ZWJ: 👨‍👩‍👧‍👦 = 4 supplementary emojis + 3 ZWJ joins.
        // Each supplementary emoji = 2 UTF-16 units; each ZWJ (U+200D) = 1.
        // Total: (2 * 4) + (1 * 3) = 11.
        $length = Utf16::length("\xF0\x9F\x91\xA8\xE2\x80\x8D\xF0\x9F\x91\xA9\xE2\x80\x8D\xF0\x9F\x91\xA7\xE2\x80\x8D\xF0\x9F\x91\xA6");

        self::assertSame(11, $length);
    }

    #[Test]
    public function empty_string_length(): void
    {
        $length = Utf16::length('');

        self::assertSame(0, $length);
    }

    #[Test]
    #[DataProvider('provideAsciiBoundaries')]
    public function ascii_boundary_returns_one_per_byte(string $value, int $expected): void
    {
        $length = Utf16::length($value);

        self::assertSame($expected, $length);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function provideAsciiBoundaries(): array
    {
        return [
            'single_ascii' => ['a', 1],
            'multi_ascii' => ['abcde', 5],
            'digits' => ['12345', 5],
            'symbols' => ['!@#$%', 5],
        ];
    }
}
