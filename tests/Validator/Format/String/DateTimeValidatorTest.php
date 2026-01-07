<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DateTimeValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
