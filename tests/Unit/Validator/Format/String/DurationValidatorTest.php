<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DurationValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DurationValidatorTest extends TestCase
{
    private DurationValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DurationValidator();
    }

    #[Test]
    public function valid_year_duration(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('P1Y');
        $this->validator->validate('P2Y6M');
    }

    #[Test]
    public function valid_day_duration(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('P1D');
        $this->validator->validate('P1DT12H');
    }

    #[Test]
    public function valid_time_duration(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('PT30M');
        $this->validator->validate('PT1H30M');
        $this->validator->validate('PT1H30M15S');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Duration must start with P');
        $this->validator->validate('not-a-duration');
    }

    #[Test]
    public function throw_error_for_missing_designator(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid duration format');
        $this->validator->validate('P1');
    }

    #[Test]
    public function throw_error_for_missing_designator_with_t(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Duration must have at least one component');
        $this->validator->validate('PT');
    }

    #[Test]
    public function throw_error_for_empty_duration(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Duration must have at least one component');
        $this->validator->validate('P');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }

    #[Test]
    public function validate_full_duration(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('P1Y2M3DT4H5M6S');
    }
}
