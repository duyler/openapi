<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;
use function str_repeat;
use function strlen;

#[CoversClass(EmailValidator::class)]
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
    public function valid_minimal_email(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('a@b.co');
    }

    #[Test]
    public function valid_punycode_domain_email(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('test@xn--mnchen-3ya.de');
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
    public function throw_error_for_email_without_tld(): void
    {
        $email = 'test@example';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->validator->validate($email);
    }

    #[Test]
    public function throw_error_for_tld_only_domain(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('test@com');
    }

    #[Test]
    public function throw_error_for_empty_label_in_domain(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('user@.com');
    }

    #[Test]
    public function throw_error_for_leading_dot_in_local_part(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('.user@example.com');
    }

    #[Test]
    public function throw_error_for_email_exceeding_rfc_5321_max_length(): void
    {
        $domain = str_repeat('b', 60) . '.' . str_repeat('c', 60) . '.' . str_repeat('d', 60) . '.' . str_repeat('e', 60) . '.' . str_repeat('f', 60) . '.com';
        $email = 'user@' . $domain;

        $this->assertGreaterThan(254, strlen($email));

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Email exceeds RFC 5321 max length (254)');

        $this->validator->validate($email);
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }

    #[Test]
    public function empty_string_email_rejection_carries_format_name_and_value(): void
    {
        $emptyEmail = '';

        $exception = null;

        try {
            $this->validator->validate($emptyEmail);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'Empty string email must be rejected');

        if (null !== $exception) {
            $this->assertSame('email', $exception->format);
            $this->assertSame($emptyEmail, $exception->value(reveal: true));
        }
    }

    #[Test]
    public function idn_local_part_email_rejection_documents_smtputf8_limitation(): void
    {
        $idnEmail = 'test@münchen.de';

        $exception = null;

        try {
            $this->validator->validate($idnEmail);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull(
            $exception,
            'Email with non-ASCII characters must be rejected by current validator. '
            . 'SMTPUTF8 (RFC 6531) internationalized mailboxes are out of scope.',
        );

        if (null !== $exception) {
            $this->assertSame('email', $exception->format);
            $this->assertSame($idnEmail, $exception->value(reveal: true));
        }
    }

    /**
     * Email edge cases across the three validation layers: EMAIL_PATTERN
     * regex (named groups local/domain, TLD >= 2), MAX_EMAIL=254 length cap,
     * and filter_var as the final defence layer.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function emailEdgeCasesProvider(): array
    {
        return [
            'empty string is rejected' => ['', false],
            'whitespace-only is rejected' => ['   ', false],
            'no-TLD "test@example" is rejected (regex requires TLD >= 2 letters)' => ['test@example', false],
            'TLD-only "test@com" is rejected (no dot in domain)' => ['test@com', false],
            'empty domain label "user@.com" is rejected' => ['user@.com', false],
            'IDN email "test@münchen.de" is rejected (SMTPUTF8 out of scope)' => ['test@münchen.de', false],
            'IDN email "test@北京.cn" is rejected (SMTPUTF8 out of scope)' => ['test@北京.cn', false],
            'punycode domain "test@xn--mnchen-3ya.de" is valid' => ['test@xn--mnchen-3ya.de', true],
            'simple email "test@example.com" is valid' => ['test@example.com', true],
            'minimal email "a@b.co" is valid' => ['a@b.co', true],
            'email with subdomain is valid' => ['user@mail.example.com', true],
            'email with plus tag is valid' => ['user+tag@example.com', true],
            'email with dots is valid' => ['first.last@example.com', true],
            'email missing @ is rejected' => ['invalid-email', false],
            'email with empty domain "test@" is rejected' => ['test@', false],
        ];
    }

    #[DataProvider('emailEdgeCasesProvider')]
    #[Test]
    public function email_edge_cases_match_expected_result(string $email, bool $expectedValid): void
    {
        $exception = null;

        try {
            $this->validator->validate($email);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertSame(
            $expectedValid,
            null === $exception,
            sprintf(
                'Email "%s" was expected to be %s but is %s',
                $email,
                $expectedValid ? 'valid' : 'invalid',
                null === $exception ? 'valid' : 'invalid: ' . $exception->getMessage(),
            ),
        );
    }
}
