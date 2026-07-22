<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IdnEmailValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

#[CoversClass(IdnEmailValidator::class)]
final class IdnEmailValidatorTest extends TestCase
{
    private IdnEmailValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IdnEmailValidator();
    }

    #[Test]
    public function idn_email_validator_accepts_unicode_email(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for SMTPUTF8 (RFC 6531) validation');
        }

        $this->expectNotToPerformAssertions();
        $this->validator->validate("\u{7528}\u{6237}@\u{4f8b}\u{5b50}.\u{5e7f}\u{544a}");
        $this->validator->validate('test@münchen.de');
        $this->validator->validate('test@北京.cn');
    }

    #[Test]
    public function idn_email_validator_accepts_ascii_email(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user@example.com');
        $this->validator->validate('John.Doe@sub.example.co.uk');
        $this->validator->validate('a@b.co');
    }

    #[Test]
    public function idn_email_validator_accepts_domain_literal(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user@[127.0.0.1]');
        $this->validator->validate('user@[IPv6:2001:db8::1]');
    }

    #[Test]
    public function idn_email_validator_rejects_invalid_email(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not-an-email');
    }

    #[Test]
    public function idn_email_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate('invalid');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('idn-email', $exception->format);
    }

    #[Test]
    public function idn_email_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(42);
    }
}
