<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\TimeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeValidator::class)]
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
            'with Z timezone' => ['10:30:00Z'],
            'with lowercase z timezone' => ['10:30:00z'],
            'with positive offset' => ['10:30:00+03:00'],
            'with negative offset' => ['10:30:00-05:00'],
            'with negative zero offset equivalent to UTC' => ['10:30:00-00:00'],
            'with milliseconds and Z' => ['10:30:00.123Z'],
            'with milliseconds and offset' => ['10:30:00.123+03:00'],
            'with fractional seconds and Z' => ['10:30:00.5Z'],
            'midnight with offset' => ['00:00:00+00:00'],
            'midnight Z' => ['00:00:00Z'],
            'mid-day UTC regression' => ['12:34:56Z'],
            'leap second with Z' => ['23:59:60Z'],
            'leap second with positive UTC offset' => ['23:59:60+00:00'],
            'leap second with negative UTC offset' => ['23:59:60-00:00'],
            'leap second with fractional and Z' => ['23:59:60.500Z'],
            'max positive offset boundary' => ['10:30:00+14:00'],
            'max negative offset boundary' => ['10:30:00-14:00'],
            'offset hour 13 with max minute' => ['10:30:00+13:59'],
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
            'basic time without offset' => ['10:30:00'],
            'end of day without offset' => ['23:59:59'],
            'midnight without offset' => ['00:00:00'],
            'noon without offset' => ['12:00:00'],
            'milliseconds without offset' => ['10:30:00.123'],
            'fractional seconds without offset' => ['10:30:00.5'],
            'hour 24' => ['24:00:00'],
            'minute 60' => ['10:60:00'],
            'second 60 outside leap second' => ['10:30:60'],
            'leap second without offset' => ['23:59:60'],
            'leap second with non-UTC offset' => ['23:59:60+05:00'],
            'leap second with max positive offset' => ['23:59:60+14:00'],
            'leap second fractional without offset' => ['23:59:60.500'],
            'random text' => ['invalid-time'],
            'date instead of time' => ['2024-01-01'],
            'time without seconds' => ['10:30'],
            'invalid timezone suffix' => ['10:30:00XYZ'],
            'letters in time' => ['aa:bb:cc'],
            'negative time' => ['-10:30:00'],
            'offset exceeds plus fourteen hours' => ['10:30:00+15:00'],
            'offset exceeds minus fourteen hours' => ['10:30:00-15:00'],
            'offset minutes exceed fifty nine' => ['10:30:00+03:60'],
            'positive boundary hour with non-zero minute' => ['10:30:00+14:01'],
            'positive boundary hour with max minute' => ['10:30:00+14:59'],
            'negative boundary hour with non-zero minute' => ['10:30:00-14:01'],
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
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }

    #[Test]
    public function validation_is_independent_from_global_state(): void
    {
        DateTime::createFromFormat('H:i:s', '99:99:99');

        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00Z');
    }

    #[Test]
    public function time_leap_second_utc_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('23:59:60Z');
    }

    #[Test]
    public function time_leap_second_non_utc_rejected(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Leap second requires UTC end-of-day');

        $this->validator->validate('23:59:60+05:00');
    }

    #[Test]
    public function time_leap_second_without_offset_rejected(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('23:59:60');
    }

    #[Test]
    public function r4_spec_006_time_without_offset_is_rejected(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('10:30:00');
    }

    #[Test]
    public function r4_spec_006_time_with_utc_z_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00Z');
    }

    #[Test]
    public function r4_spec_006_time_with_lowercase_z_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00z');
    }

    #[Test]
    public function r4_spec_006_time_with_positive_numeric_offset_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00+03:00');
    }

    #[Test]
    public function r4_spec_006_time_with_negative_zero_offset_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00-00:00');
    }

    #[Test]
    public function r4_spec_006_time_with_max_positive_offset_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00+14:00');
    }

    #[Test]
    public function r4_spec_006_time_with_max_negative_offset_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00-14:00');
    }

    #[Test]
    public function r4_spec_006_time_with_offset_above_max_is_rejected(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('10:30:00+15:00');
    }

    #[Test]
    public function r4_spec_006_time_with_fractional_seconds_and_utc_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('10:30:00.5Z');
    }

    #[Test]
    public function r4_spec_006_time_with_fractional_seconds_without_offset_is_rejected(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('10:30:00.5');
    }
}
