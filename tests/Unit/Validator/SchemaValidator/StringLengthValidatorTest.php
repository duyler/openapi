<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Util\Utf16;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(StringLengthValidator::class)]
class StringLengthValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private StringLengthValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new StringLengthValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function validate_min_length(): void
    {
        $schema = new Schema(type: 'string', minLength: 3);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_max_length(): void
    {
        $schema = new Schema(type: 'string', maxLength: 10);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_both_min_and_max(): void
    {
        $schema = new Schema(type: 'string', minLength: 3, maxLength: 10);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_min_length_error(): void
    {
        $schema = new Schema(type: 'string', minLength: 5);

        $this->expectException(MinLengthError::class);

        $this->validator->validate('hi', $schema);
    }

    #[Test]
    public function throw_max_length_error(): void
    {
        $schema = new Schema(type: 'string', maxLength: 3);

        $this->expectException(MaxLengthError::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function skip_validation_for_non_string(): void
    {
        $schema = new Schema(type: 'integer', minLength: 3);

        $this->validator->validate(123, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unicode_string_length(): void
    {
        $schema = new Schema(type: 'string', minLength: 3);

        $this->validator->validate('Привет', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_string(): void
    {
        $schema = new Schema(type: 'string', minLength: 0);

        $this->validator->validate('', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_string_when_min_greater_than_zero(): void
    {
        $schema = new Schema(type: 'string', minLength: 1);

        $this->expectException(MinLengthError::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function skip_when_no_length_constraints(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any string', $schema);

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function provideMultiByteStrings(): array
    {
        return [
            'emoji_family_zwj' => ["\xF0\x9F\x91\xA8\xE2\x80\x8D\xF0\x9F\x91\xA9\xE2\x80\x8D\xF0\x9F\x91\xA7\xE2\x80\x8D\xF0\x9F\x91\xA6", 11],
            'cjk_4_byte_utf8' => ["\xF0\xA0\xAE\xB7", 2],
            'russian_cyrillic' => ['Привет', 6],
            'ascii_single' => ['a', 1],
        ];
    }

    #[Test]
    #[DataProvider('provideMultiByteStrings')]
    public function multi_byte_string_passes_min_length_one(string $value, int $expectedUtf16Units): void
    {
        self::assertSame($expectedUtf16Units, Utf16::length($value));

        $schema = new Schema(type: 'string', minLength: 1);

        $succeeded = false;

        try {
            $this->validator->validate($value, $schema);
            $succeeded = true;
        } catch (MinLengthError|MaxLengthError $e) {
            self::fail(sprintf('Expected multi-byte string to pass minLength:1, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    #[DataProvider('provideMultiByteStrings')]
    public function multi_byte_string_passes_max_length_equal_to_utf16_units(string $value, int $expectedUtf16Units): void
    {
        $schema = new Schema(type: 'string', maxLength: $expectedUtf16Units);

        $succeeded = false;

        try {
            $this->validator->validate($value, $schema);
            $succeeded = true;
        } catch (MaxLengthError $e) {
            self::fail(sprintf('Expected multi-byte string to pass maxLength:%d, got: %s', $expectedUtf16Units, $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function emoji_family_at_max_length_one_rejected_by_utf16_unit_count(): void
    {
        $emoji = "\xF0\x9F\x91\xA8\xE2\x80\x8D\xF0\x9F\x91\xA9\xE2\x80\x8D\xF0\x9F\x91\xA7\xE2\x80\x8D\xF0\x9F\x91\xA6";
        $schema = new Schema(type: 'string', maxLength: 1);

        $caught = null;

        try {
            $this->validator->validate($emoji, $schema);
        } catch (MaxLengthError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('maxLength', $caught->keyword());
        self::assertSame('/maxLength', $caught->schemaPath());
        self::assertSame(1, $caught->params()['maxLength']);
        self::assertSame(11, $caught->params()['actual']);
    }

    #[Test]
    public function supplementary_cjk_at_max_length_one_rejected_by_utf16_unit_count(): void
    {
        // U+20BB7 lives in the supplementary plane: 4 bytes in UTF-8,
        // encoded as a UTF-16 surrogate pair (2 code units). Therefore
        // it must be rejected by maxLength:1 even though it is a single
        // Unicode code point.
        $cjk = "\xF0\xA0\xAE\xB7";
        $schema = new Schema(type: 'string', maxLength: 1);

        $caught = null;

        try {
            $this->validator->validate($cjk, $schema);
        } catch (MaxLengthError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(2, $caught->params()['actual']);
    }

    #[Test]
    public function ascii_single_char_at_min_length_two_rejected(): void
    {
        $schema = new Schema(type: 'string', minLength: 2);

        $caught = null;

        try {
            $this->validator->validate('a', $schema);
        } catch (MinLengthError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('minLength', $caught->keyword());
        self::assertSame('/minLength', $caught->schemaPath());
        self::assertSame(2, $caught->params()['minLength']);
        self::assertSame(1, $caught->params()['actual']);
    }

    /**
     * P-011: JSON is always UTF-8 per RFC 8259 §8.1; the validator MUST
     * count UTF-16 code units independent of any host application
     * mb_internal_encoding override (e.g., ASCII) that would otherwise
     * fall back to byte counting.
     */
    #[Test]
    public function counts_multibyte_utf8_under_overridden_internal_encoding(): void
    {
        $previousEncoding = mb_internal_encoding();
        mb_internal_encoding('ASCII');

        try {
            $schema = new Schema(type: 'string', maxLength: 1);

            $succeeded = false;

            try {
                $this->validator->validate('é', $schema);
                $succeeded = true;
            } catch (MaxLengthError $e) {
                self::fail(sprintf(
                    'Expected "é" (1 UTF-16 unit) to pass maxLength:1 even under ASCII internal encoding, got: %s',
                    $e->getMessage(),
                ));
            }

            self::assertSame(true, $succeeded);
        } finally {
            mb_internal_encoding($previousEncoding);
        }
    }

    /**
     * SPEC-06: supplementary characters (U+10000+) occupy 2 UTF-16 code
     * units via surrogate pairs. The byte-based counter must remain
     * correct regardless of mb_internal_encoding, since JSON Schema
     * 2020-12 §6.3.1 mandates UTF-16 unit counting.
     */
    #[Test]
    public function counts_emoji_as_two_utf16_units_under_ascii_internal_encoding(): void
    {
        $previousEncoding = mb_internal_encoding();
        mb_internal_encoding('ASCII');

        try {
            $schema = new Schema(type: 'string', maxLength: 2);

            $this->validator->validate('👍', $schema);

            $this->expectNotToPerformAssertions();
        } finally {
            mb_internal_encoding($previousEncoding);
        }
    }

    /**
     * SPEC-06 (anti-test): supplementary characters must be rejected
     * when maxLength is below their UTF-16 unit count, regardless of
     * the host's mb_internal_encoding setting.
     */
    #[Test]
    public function rejects_emoji_at_max_length_one_under_ascii_internal_encoding(): void
    {
        $previousEncoding = mb_internal_encoding();
        mb_internal_encoding('ASCII');

        try {
            $schema = new Schema(type: 'string', maxLength: 1);

            $this->expectException(MaxLengthError::class);

            $this->validator->validate('👍', $schema);
        } finally {
            mb_internal_encoding($previousEncoding);
        }
    }

    /**
     * SPEC-06: minLength counts UTF-16 code units, so a single emoji
     * (2 units) satisfies minLength:2 — the exact case that the old
     * mb_strlen($data, 'UTF-8') implementation rejected incorrectly.
     */
    #[Test]
    public function supplementary_emoji_passes_min_length_two(): void
    {
        $schema = new Schema(type: 'string', minLength: 2);

        $this->validator->validate('😀', $schema);

        $this->expectNotToPerformAssertions();
    }

    /**
     * SPEC-06: minLength counts UTF-16 code units, so a single emoji
     * (2 units) must be rejected by minLength:3 (which requires at
     * least 3 UTF-16 units).
     */
    #[Test]
    public function supplementary_emoji_fails_min_length_three(): void
    {
        $schema = new Schema(type: 'string', minLength: 3);

        $this->expectException(MinLengthError::class);

        $this->validator->validate('😀', $schema);
    }

    /**
     * SPEC-06 boundary: two supplementary emojis (4 UTF-16 units)
     * must satisfy maxLength:4 exactly.
     */
    #[Test]
    public function supplementary_pair_at_max_length_boundary(): void
    {
        $schema = new Schema(type: 'string', maxLength: 4);

        $this->validator->validate('😀😀', $schema);

        $this->expectNotToPerformAssertions();
    }
}
