<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\HostnameValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HostnameValidatorTest extends TestCase
{
    private HostnameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new HostnameValidator();
    }

    #[Test]
    public function valid_simple_hostname(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('example.com');
        $this->validator->validate('localhost');
    }

    #[Test]
    public function valid_subdomain_hostname(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('mail.example.com');
        $this->validator->validate('sub.sub.example.com');
    }

    #[Test]
    public function throw_error_for_invalid_characters(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid hostname format');
        $this->validator->validate('exam_ple.com');
    }

    #[Test]
    public function throw_error_for_too_long(): void
    {
        $longHostname = str_repeat('a', 254) . '.com';
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate($longHostname);
    }

    #[Test]
    public function throw_error_for_label_too_long(): void
    {
        $longLabel = str_repeat('a', 64) . '.com';
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate($longLabel);
    }

    #[Test]
    public function valid_hostname_with_www(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('www.example.com');
    }

    #[Test]
    public function valid_hostname_with_numbers(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('server123.example.com');
    }

    #[Test]
    public function valid_hostname_with_hyphens(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('my-server.example.com');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
