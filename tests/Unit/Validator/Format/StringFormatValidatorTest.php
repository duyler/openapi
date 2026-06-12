<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\DurationValidator;
use Duyler\OpenApi\Validator\Format\String\HostnameValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class StringFormatValidatorTest extends TestCase
{
    #[Test]
    public function duration_validator_accepts_valid_duration(): void
    {
        $validator = new DurationValidator();
        $validator->validate('P3Y6M4DT12H30M5S');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function duration_validator_accepts_date_only(): void
    {
        $validator = new DurationValidator();
        $validator->validate('P1Y2M3D');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function duration_validator_accepts_time_only(): void
    {
        $validator = new DurationValidator();
        $validator->validate('PT12H30M5S');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function duration_validator_rejects_non_p_start(): void
    {
        $validator = new DurationValidator();
        $this->expectException(InvalidFormatException::class);
        $validator->validate('3Y6M4D');
    }

    #[Test]
    public function duration_validator_rejects_empty_components(): void
    {
        $validator = new DurationValidator();
        $this->expectException(InvalidFormatException::class);
        $validator->validate('P');
    }

    #[Test]
    public function duration_validator_rejects_invalid_format(): void
    {
        $validator = new DurationValidator();
        $this->expectException(InvalidFormatException::class);
        $validator->validate('Pabc');
    }

    #[Test]
    public function hostname_validator_accepts_valid_hostname(): void
    {
        $validator = new HostnameValidator();
        $validator->validate('example.com');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hostname_validator_accepts_localhost(): void
    {
        $validator = new HostnameValidator();
        $validator->validate('localhost');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hostname_validator_accepts_subdomain(): void
    {
        $validator = new HostnameValidator();
        $validator->validate('sub.domain.example.com');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hostname_validator_rejects_too_long(): void
    {
        $validator = new HostnameValidator();

        $longHostname = str_repeat('a', 254) . '.com';
        $this->expectException(InvalidFormatException::class);
        $validator->validate($longHostname);
    }

    #[Test]
    public function hostname_validator_rejects_invalid_format(): void
    {
        $validator = new HostnameValidator();
        $this->expectException(InvalidFormatException::class);
        $validator->validate('-invalid.com');
    }

    #[Test]
    public function hostname_validator_rejects_hyphen_start(): void
    {
        $validator = new HostnameValidator();
        $this->expectException(InvalidFormatException::class);
        $validator->validate('-example.com');
    }

    #[Test]
    public function hostname_validator_rejects_hyphen_end(): void
    {
        $validator = new HostnameValidator();
        $this->expectException(InvalidFormatException::class);
        $validator->validate('example-.com');
    }
}
