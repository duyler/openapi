<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\TimeValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeValidatorTest extends TestCase
{
    private TimeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TimeValidator();
    }

    #[Test]
    public function valid_time_format(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('10:30:00');
        $this->validator->validate('23:59:59');
        $this->validator->validate('00:00:00');
    }

    #[Test]
    public function validate_with_timezone_offset(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('10:30:00Z');
        $this->validator->validate('10:30:00+03:00');
        $this->validator->validate('10:30:00-05:00');
    }

    #[Test]
    public function throw_error_for_invalid_hour(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('24:00:00');
    }

    #[Test]
    public function throw_error_for_invalid_minute(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('10:60:00');
    }

    #[Test]
    public function throw_error_for_invalid_second(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('10:30:60');
    }
}
