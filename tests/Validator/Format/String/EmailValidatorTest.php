<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailValidatorTest extends TestCase
{
    private EmailValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new EmailValidator();
    }

    #[Test]
    public function valid_simple_email(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('test@example.com');
    }

    #[Test]
    public function valid_email_with_subdomain(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user@mail.example.com');
    }

    #[Test]
    public function valid_email_with_plus(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user+tag@example.com');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid email format');
        $this->validator->validate('invalid-email');
    }

    #[Test]
    public function throw_error_for_missing_at_sign(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('example.com');
    }

    #[Test]
    public function throw_error_for_invalid_domain(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('test@');
    }

    #[Test]
    public function valid_email_with_dots(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('first.last@example.com');
    }

    #[Test]
    public function valid_email_with_underscores(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user_name@example.com');
    }

    #[Test]
    public function valid_email_with_hyphens(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user-name@example.com');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
