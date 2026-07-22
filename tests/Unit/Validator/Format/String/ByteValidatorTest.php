<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\ByteValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ByteValidator::class)]
final class ByteValidatorTest extends TestCase
{
    private ByteValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ByteValidator();
    }

    public static function validByteValuesProvider(): array
    {
        return [
            'standard text' => [base64_encode('Hello, World!')],
            'empty string' => [''],
            'unicode text' => [base64_encode('Привет мир')],
            'binary data' => [base64_encode(pack('C*', 0, 1, 2, 3, 255))],
            'single character' => [base64_encode('a')],
            'long string' => [base64_encode(str_repeat('x', 1000))],
            'json payload' => [base64_encode('{"key":"value"}')],
            'padding with single equals' => ['SGVsbG8='],
            'padding with double equals' => ['SGVs'],
            'url-safe with hyphen' => ['aGVsbG8-d29ybGQ='],
            'url-safe with underscore' => ['aGVsbG8_d29ybGQ='],
            'url-safe real bytes' => ['_78='],
            'url-safe real bytes with both chars' => ['-_8='],
        ];
    }

    #[DataProvider('validByteValuesProvider')]
    #[Test]
    public function valid_byte_values_pass(string $value): void
    {
        $this->validator->validate($value);

        $this->assertTrue(true);
    }

    public static function invalidByteValuesProvider(): array
    {
        return [
            'special characters' => ['!@#$%^&*()'],
            'spaces only' => ['   '],
            'incomplete base64' => ['SGVsbG8'],
            'invalid padding' => ['SGVsbG8= ='],
            'random symbols' => ['@@@!!!'],
            'exclamation garbage' => ['!!!not-base64!!!'],
            'whitespace inside valid-looking base64' => ["SGVs\nbG8="],
            'url-safe without padding fails round trip' => ['aGVsbG8-d29ybGQ'],
        ];
    }

    #[DataProvider('invalidByteValuesProvider')]
    #[Test]
    public function invalid_byte_values_throw_exception(string $value): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($value);
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');

        $this->validator->validate(123);
    }

    #[Test]
    public function throw_error_for_null(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate(null);
    }

    #[Test]
    public function throw_error_for_array(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate(['SGVsbG8=']);
    }

    #[Test]
    public function exception_contains_format_name(): void
    {
        try {
            $this->validator->validate('!invalid!');
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('byte', $exception->format);
        }
    }

    #[Test]
    public function exception_contains_invalid_value(): void
    {
        $invalidValue = '!invalid!';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }

    #[Test]
    public function valid_base64_round_trip(): void
    {
        $original = 'Test data for round trip';

        $encoded = base64_encode($original);

        $this->validator->validate($encoded);

        $this->assertSame($original, base64_decode($encoded));
    }

    #[Test]
    public function url_safe_base64_with_hyphen_passes(): void
    {
        $original = 'hello>world';
        $urlSafe = strtr(base64_encode($original), '+/', '-_');

        $exception = null;

        try {
            $this->validator->validate($urlSafe);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            'Url-safe base64 (RFC 4648 §5) must be accepted. Failed: ' . ($exception?->getMessage() ?? ''),
        );
    }

    #[Test]
    public function url_safe_base64_with_underscore_passes(): void
    {
        $bytes = pack('C*', 0xfb, 0xff);
        $urlSafe = strtr(base64_encode($bytes), '+/', '-_');

        $exception = null;

        try {
            $this->validator->validate($urlSafe);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            'Url-safe base64 (RFC 4648 §5) with underscore must be accepted.',
        );
    }

    #[Test]
    public function invalid_base64_error_message_mentions_standards(): void
    {
        $invalid = '!!!not-base64!!!';

        try {
            $this->validator->validate($invalid);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertStringContainsString('standard', $exception->getMessage());
            $this->assertStringContainsString('url-safe', $exception->getMessage());
        }
    }
}
