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

    /**
     * Email edge cases: empty string, TLD-only, IDN (Internationalized Domain
     * Names). The validator uses PHP's filter_var with FILTER_VALIDATE_EMAIL,
     * which requires a dot in the domain part and rejects non-ASCII characters
     * (no FILTER_FLAG_EMAIL_UNICODE). This is a characterization of the
     * underlying filter_var behavior.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function emailEdgeCasesProvider(): array
    {
        return [
            'empty string is rejected' => ['', false],
            'whitespace-only is rejected' => ['   ', false],
            'TLD-only "test@com" is rejected (filter_var requires a dot in domain)' => ['test@com', false],
            'TLD-only "test@org" is rejected' => ['test@org', false],
            'IDN "test@münchen.de" is rejected (non-ASCII unsupported by filter_var)' => ['test@münchen.de', false],
            'IDN "test@北京.cn" is rejected (non-ASCII unsupported by filter_var)' => ['test@北京.cn', false],
            'IDN ASCII-encoded "test@xn--mnchen-3ya.de" is valid (punycode)' => ['test@xn--mnchen-3ya.de', true],
            'simple email "test@example.com" is valid' => ['test@example.com', true],
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
            $this->assertSame($emptyEmail, $exception->value);
        }
    }

    #[Test]
    public function idn_email_rejection_documents_filter_var_limitation(): void
    {
        $idnEmail = 'test@münchen.de';

        $exception = null;

        try {
            $this->validator->validate($idnEmail);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull(
            $exception,
            'IDN email with non-ASCII characters must be rejected by current filter_var-based validator. '
            . 'If this assertion fails, the validator has been updated to support IDN emails (FILTER_FLAG_EMAIL_UNICODE).',
        );

        if (null !== $exception) {
            $this->assertSame('email', $exception->format);
            $this->assertSame($idnEmail, $exception->value);
        }
    }
}
