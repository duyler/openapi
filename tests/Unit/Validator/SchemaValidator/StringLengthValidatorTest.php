<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
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
        $this->validator = new StringLengthValidator($this->pool, BuiltinFormats::create());
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
            'emoji_family_zwj' => ["\xF0\x9F\x91\xA8\xE2\x80\x8D\xF0\x9F\x91\xA9\xE2\x80\x8D\xF0\x9F\x91\xA7\xE2\x80\x8D\xF0\x9F\x91\xA6", 7],
            'cjk_4_byte_utf8' => ["\xF0\xA0\xAE\xB7", 1],
            'russian_cyrillic' => ['Привет', 6],
            'ascii_single' => ['a', 1],
        ];
    }

    #[Test]
    #[DataProvider('provideMultiByteStrings')]
    public function multi_byte_string_passes_min_length_one(string $value, int $expectedCodepoints): void
    {
        self::assertSame($expectedCodepoints, mb_strlen($value));

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
    public function multi_byte_string_passes_max_length_equal_to_codepoints(string $value, int $expectedCodepoints): void
    {
        $schema = new Schema(type: 'string', maxLength: $expectedCodepoints);

        $succeeded = false;

        try {
            $this->validator->validate($value, $schema);
            $succeeded = true;
        } catch (MaxLengthError $e) {
            self::fail(sprintf('Expected multi-byte string to pass maxLength:%d, got: %s', $expectedCodepoints, $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function emoji_family_at_max_length_one_rejected_by_codepoint_count(): void
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
        self::assertSame(7, $caught->params()['actual']);
    }

    #[Test]
    public function cjk_4_byte_symbol_at_max_length_one_passes_by_codepoint_count(): void
    {
        $cjk = "\xF0\xA0\xAE\xB7";
        $schema = new Schema(type: 'string', maxLength: 1);

        $succeeded = false;

        try {
            $this->validator->validate($cjk, $schema);
            $succeeded = true;
        } catch (MaxLengthError $e) {
            self::fail(sprintf('Expected CJK U+20BB7 (1 codepoint, 4 bytes) to pass maxLength:1, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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
}
