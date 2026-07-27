<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\Utf16Length;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Golden-master and unit tests for the Utf16Length collaborator. The emitted
 * code is byte-identical to the body that previously lived inline in
 * ValidatorCompiler::generateUtf16LengthComputation (extracted in task 10a).
 */
final class Utf16LengthTest extends TestCase
{
    private Utf16Length $utf16;

    protected function setUp(): void
    {
        $this->utf16 = new Utf16Length();
    }

    #[Test]
    public function generate_produces_byte_identical_output_for_default_data_var(): void
    {
        $code = $this->utf16->generate();

        // Pinned byte-for-byte from the pre-refactor ValidatorCompiler output.
        $expected = <<<'PHP'
        $utf16Length = 0;
        $utf16Bytes = strlen((string) $data);
        $utf16Pos = 0;
        while ($utf16Pos < $utf16Bytes) {
            $utf16Octet = ord($data[$utf16Pos]);
            if ($utf16Octet < 0x80) {
                $utf16Pos += 1;
                $utf16Length += 1;
            } elseif ($utf16Octet < 0xC0) {
                $utf16Pos += 1;
            } elseif ($utf16Octet < 0xE0) {
                $utf16Pos += 2;
                $utf16Length += 1;
            } elseif ($utf16Octet < 0xF0) {
                $utf16Pos += 3;
                $utf16Length += 1;
            } else {
                $utf16Pos += 4;
                $utf16Length += 2;
            }
        }

PHP;

        self::assertSame($expected, $code);
    }

    #[Test]
    public function generate_substitutes_value_var_for_nested_properties(): void
    {
        $code = $this->utf16->generate('$data[\'name\']');

        self::assertStringContainsString("\$utf16Bytes = strlen((string) \$data['name']);", $code);
        self::assertStringContainsString("\$utf16Octet = ord(\$data['name'][\$utf16Pos]);", $code);
    }

    #[Test]
    public function generate_uses_utf16_length_var_not_mb_strlen(): void
    {
        $code = $this->utf16->generate();

        self::assertStringContainsString('$utf16Length', $code);
        self::assertStringNotContainsString('mb_strlen', $code);
    }

    #[Test]
    public function generate_initialises_length_to_zero(): void
    {
        $code = $this->utf16->generate();

        self::assertStringContainsString("\$utf16Length = 0;\n", $code);
    }

    #[Test]
    public function generate_covers_all_five_utf8_leading_octet_buckets(): void
    {
        $code = $this->utf16->generate();

        // ASCII: 1 byte, 1 UTF-16 unit
        self::assertStringContainsString('$utf16Octet < 0x80', $code);
        // Continuation byte: 1 byte, no length bump (defensive — should not be a leading byte)
        self::assertStringContainsString('$utf16Octet < 0xC0', $code);
        // 2-byte sequence: 1 UTF-16 unit
        self::assertStringContainsString('$utf16Octet < 0xE0', $code);
        // 3-byte sequence: 1 UTF-16 unit
        self::assertStringContainsString('$utf16Octet < 0xF0', $code);
        // 4-byte sequence (astral plane): 2 UTF-16 units (surrogate pair)
        self::assertStringContainsString('$utf16Pos += 4;', $code);
        self::assertStringContainsString('$utf16Length += 2;', $code);
    }

    #[Test]
    public function generate_is_deterministic_across_invocations(): void
    {
        $first = $this->utf16->generate();
        $second = $this->utf16->generate();

        self::assertSame($first, $second, 'Utf16Length::generate must be deterministic.');
    }
}
