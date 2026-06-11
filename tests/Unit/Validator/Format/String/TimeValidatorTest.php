<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\TimeValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeValidatorTest extends TestCase
{
    private TimeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TimeValidator();
    }

    public static function validTimeValuesProvider(): array
    {
        return [
            'basic time' => ['10:30:00'],
            'end of day' => ['23:59:59'],
            'midnight' => ['00:00:00'],
            'with Z timezone' => ['10:30:00Z'],
            'with positive offset' => ['10:30:00+03:00'],
            'with negative offset' => ['10:30:00-05:00'],
            'with milliseconds' => ['10:30:00.123'],
            'with milliseconds and Z' => ['10:30:00.123Z'],
            'with milliseconds and offset' => ['10:30:00.123+03:00'],
            'noon' => ['12:00:00'],
            'one second before midnight' => ['23:59:59'],
            'midnight with offset' => ['00:00:00+00:00'],
            'midnight Z' => ['00:00:00Z'],
        ];
    }

    #[DataProvider('validTimeValuesProvider')]
    #[Test]
    public function valid_time_values_pass(string $value): void
    {
        $this->validator->validate($value);

        $this->assertTrue(true);
    }

    public static function invalidTimeValuesProvider(): array
    {
        return [
            'hour 24' => ['24:00:00'],
            'minute 60' => ['10:60:00'],
            'second 60' => ['10:30:60'],
            'random text' => ['invalid-time'],
            'date instead of time' => ['2024-01-01'],
            'time without seconds' => ['10:30'],
            'invalid timezone suffix' => ['10:30:00XYZ'],
            'letters in time' => ['aa:bb:cc'],
            'negative time' => ['-10:30:00'],
        ];
    }

    #[DataProvider('invalidTimeValuesProvider')]
    #[Test]
    public function invalid_time_values_throw_exception(string $value): void
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
    public function exception_contains_format_name(): void
    {
        try {
            $this->validator->validate('invalid');
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('time', $exception->format);
        }
    }

    #[Test]
    public function exception_contains_invalid_value(): void
    {
        $invalidValue = 'invalid-time';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame($invalidValue, $exception->value);
        }
    }
}
