<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\Ipv6Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Ipv6ValidatorTest extends TestCase
{
    private Ipv6Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Ipv6Validator();
    }

    #[Test]
    public function valid_ipv6(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->validator->validate('2001:db8:85a3::8a2e:370:7334');
    }

    #[Test]
    public function valid_compressed_ipv6(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('::1');
        $this->validator->validate('2001:db8::1');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv6 address format');
        $this->validator->validate('not-an-ipv6');
    }

    #[Test]
    public function throw_error_for_invalid_hex(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv6 address format');
        $this->validator->validate('2001:0db8:85a3:0000:0000:8g2e:0370:7334');
    }

    #[Test]
    public function validate_ipv4_mapped_ipv6(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('::ffff:192.168.1.1');
    }

    #[Test]
    public function valid_localhost(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('::1');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }

    #[Test]
    public function throw_error_for_too_long(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid IPv6 address format');
        $this->validator->validate('2001:0db8:85a3:0000:0000:8a2e:0370:7334:1234');
    }
}
