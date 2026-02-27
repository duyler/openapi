<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DateValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DateValidatorTest extends TestCase
{
    private DateValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DateValidator();
    }

    #[Test]
    public function valid_date_format(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('2024-01-15');
        $this->validator->validate('2024-12-31');
        $this->validator->validate('2020-02-29');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid date format');
        $this->validator->validate('2024/01/15');
    }

    #[Test]
    public function throw_error_for_impossible_date(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('2025-02-30');
    }

    #[Test]
    public function validate_leap_year(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('2024-02-29');
    }

    #[Test]
    public function throw_error_for_invalid_month(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid date value');
        $this->validator->validate('2024-13-01');
    }

    #[Test]
    public function throw_error_for_invalid_day(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid date value');
        $this->validator->validate('2024-01-32');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(20240115);
    }
}
