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
            $this->assertSame($invalidValue, $exception->value);
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
}
