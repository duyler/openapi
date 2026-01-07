<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

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
}
