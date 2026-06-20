<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use DateTime;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DateTimeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateTimeValidator::class)]
final class DateTimeValidatorTest extends TestCase
{
    private DateTimeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DateTimeValidator();
    }

    #[Test]
    public function validate_rfc3339_datetime(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2024-01-15T10:30:00Z');
        $this->validator->validate('2024-01-15T10:30:00+03:00');
        $this->validator->validate('2024-01-15T10:30:00-05:00');
    }

    #[Test]
    public function validate_with_timezone(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2024-01-15T10:30:00+03:00');
        $this->validator->validate('2024-01-15T10:30:00-05:00');
        $this->validator->validate('2024-01-15T10:30:00Z');
    }

    #[Test]
    public function validate_with_microseconds(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2024-01-15T10:30:00.123Z');
    }

    #[Test]
    public function validate_fractional_seconds_with_offset(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2026-01-15T10:30:00.123456+03:00');
    }

    #[Test]
    public function validate_leap_second_per_rfc3339(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2016-12-31T23:59:60Z');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid date-time format');

        $this->validator->validate('invalid-date');
    }

    #[Test]
    public function throw_error_for_invalid_date(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2024-02-30T10:30:00Z');
    }

    #[Test]
    public function throw_error_for_missing_timezone(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2024-01-15T10:30:00');
    }

    #[Test]
    public function throw_error_for_invalid_time(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2024-01-15T25:30:00Z');
    }

    #[Test]
    public function throw_error_for_invalid_value(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid date-time format');

        $this->validator->validate('2024-13-01T10:30:00Z');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');

        $this->validator->validate(123456);
    }

    #[Test]
    public function reject_date_without_time_component(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2026-01-15');
    }

    #[Test]
    public function reject_single_digit_month_or_day(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2026-1-5T10:30:00Z');
    }

    #[Test]
    public function reject_leap_second_outside_end_of_day(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2026-01-15T10:30:60Z');
    }

    #[Test]
    public function validate_max_positive_offset_boundary(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2024-01-15T10:30:00+14:00');
    }

    #[Test]
    public function validate_max_negative_offset_boundary(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('2024-01-15T10:30:00-14:00');
    }

    #[Test]
    public function reject_offset_above_max_positive_boundary_minute(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2024-01-15T10:30:00+14:01');
    }

    #[Test]
    public function reject_offset_at_boundary_hour_with_max_minute(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2024-01-15T10:30:00+14:59');
    }

    #[Test]
    public function reject_offset_below_max_negative_boundary_minute(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate('2024-01-15T10:30:00-14:01');
    }

    #[Test]
    public function validation_is_independent_from_global_state(): void
    {
        DateTime::createFromFormat('Y-m-d', '2026-13-99');

        $this->expectNotToPerformAssertions();

        $this->validator->validate('2026-01-15T10:30:00Z');
    }
}
