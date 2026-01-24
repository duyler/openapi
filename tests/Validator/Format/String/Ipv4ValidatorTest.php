<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\Ipv4Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Ipv4ValidatorTest extends TestCase
{
    private Ipv4Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Ipv4Validator();
    }

    #[Test]
    public function valid_ipv4(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('192.168.1.1');
        $this->validator->validate('10.0.0.1');
        $this->validator->validate('255.255.255.255');
    }

    #[Test]
    public function valid_localhost(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('127.0.0.1');
    }

    #[Test]
    public function throw_error_for_out_of_range(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv4 address format');
        $this->validator->validate('256.168.1.1');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv4 address format');
        $this->validator->validate('192.168.1');
    }

    #[Test]
    public function throw_error_for_missing_octets(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv4 address format');
        $this->validator->validate('192.168.1');
    }

    #[Test]
    public function valid_all_octets_zero(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('0.0.0.0');
    }

    #[Test]
    public function valid_all_octets_max(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('255.255.255.255');
    }

    #[Test]
    public function valid_private_range(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('192.168.0.1');
        $this->validator->validate('10.0.0.1');
        $this->validator->validate('172.16.0.1');
    }

    #[Test]
    public function throw_error_for_negative_octet(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv4 address format');
        $this->validator->validate('192.-1.1.1');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
