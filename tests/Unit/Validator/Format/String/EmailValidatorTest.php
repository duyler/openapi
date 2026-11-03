<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IdnEmailValidator;
use Duyler\OpenApi\Validator\Format\String\EmailValidator;
use Duyler\OpenApi\Validator\PregExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
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
    public function email_validator_accepts_ipv4_literal(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user@[127.0.0.1]');
        $this->validator->validate('user@[192.168.1.1]');
        $this->validator->validate('user@[10.0.0.1]');
    }

    #[Test]
    public function email_validator_rejects_invalid_ipv4_literal(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid email format');
        $this->validator->validate('user@[999.999.999.999]');
    }

    #[Test]
    public function email_validator_accepts_ipv6_literal(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user@[IPv6:2001:db8::1]');
        $this->validator->validate('user@[IPv6:::1]');
        $this->validator->validate('user@[IPv6:fe80::1]');
    }

    #[Test]
    public function email_validator_accepts_quoted_local_part_with_space(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('"hello world"@example.com');
        $this->validator->validate('"john doe"@sub.example.co.uk');
    }

    #[Test]
    public function email_validator_accepts_quoted_at_symbol(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('"@"@example.com');
        $this->validator->validate('"user@domain"@example.com');
    }

    #[Test]
    public function email_validator_accepts_quoted_escaped_quote(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('"hello\"world"@example.com');
        $this->validator->validate('"back\\slash"@example.com');
    }

    #[Test]
    public function email_validator_rejects_quoted_local_failures(): void
    {
        $cases = [
            'dangling backslash at end of quoted string' => '"test' . '\\',
            'non-ascii character in a quoted pair' => "\"test\\\u{A9}\"@iana.org",
            'control character inside quoted string' => "\"test\u{7F}\"@iana.org",
            'quoted local without domain separator' => '"test"',
            'local part longer than 64 octets' => str_repeat('a', 65) . '@example.com',
        ];

        foreach ($cases as $label => $value) {
            $exception = null;

            try {
                $this->validator->validate($value);
            } catch (InvalidFormatException $exception) {
            }

            $this->assertNotNull($exception, sprintf('%s must be rejected', $label));
            $this->assertSame('email', $exception->format);
        }
    }

    #[Test]
    public function email_validator_rejects_address_literal_failures(): void
    {
        $cases = [
            'unclosed address literal' => 'test@[1.2.3.4',
            'empty brackets after the at sign' => 'a@[]',
            'three octets in address literal' => 'a@[1.2.3]',
            'five octets in address literal' => 'a@[1.2.3.4.5]',
            'octet above 255 in address literal' => 'joe.bloggs@[127.0.0.300]',
            'bracketed content that is not an address' => 'test@[RFC-5322-domain-literal]',
            'ipv6 tag with invalid address' => 'a@[IPv6:gggg::1]',
        ];

        foreach ($cases as $label => $value) {
            $exception = null;

            try {
                $this->validator->validate($value);
            } catch (InvalidFormatException $exception) {
            }

            $this->assertNotNull($exception, sprintf('%s must be rejected', $label));
            $this->assertSame('email', $exception->format);
        }
    }

    #[Test]
    public function email_validator_rejects_smtputf8_per_rfc_5321(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->validator->validate('用户@例子.广告');
    }

    #[Test]
    public function idn_email_validator_accepts_smtputf8_per_rfc_6531(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for SMTPUTF8 (RFC 6531) validation');
        }

        $this->expectNotToPerformAssertions();

        $idnEmailValidator = new IdnEmailValidator();

        $idnEmailValidator->validate('用户@例子.广告');
        $idnEmailValidator->validate('test@münchen.de');
        $idnEmailValidator->validate('test@北京.cn');
    }

    #[Test]
    public function email_validator_ascii_regression(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('user@example.com');
        $this->validator->validate('John.Doe@sub.example.co.uk');
        $this->validator->validate('a@b.co');
        $this->validator->validate('test@xn--mnchen-3ya.de');
    }

    #[Test]
    public function email_validator_accepts_single_label_domain_per_rfc_5321(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('test@io');
        $this->validator->validate('test@com');
        $this->validator->validate('test@example');
    }

    #[Test]
    public function email_validator_accepts_domain_label_at_63_octets(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('user@' . str_repeat('a', 63) . '.com');
    }

    #[Test]
    public function email_validator_rejects_domain_label_longer_than_63_octets(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->validator->validate('user@' . str_repeat('a', 64) . '.com');
    }

    #[Test]
    public function email_validator_fails_closed_on_pcre_runtime_failure(): void
    {
        $validator = new EmailValidator(new PregExecutor(maxBacktracks: 1));

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid email format');

        $validator->validate('user@' . str_repeat('a', 100) . 'b');
    }

    #[Test]
    public function email_validator_rejects_non_ascii_local_and_domain(): void
    {
        $exception = null;

        try {
            $this->validator->validate('aé@iana.org');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'email format is RFC 5321 ASCII-only; non-ASCII local must be rejected');

        $exception = null;

        try {
            $this->validator->validate('a@é.org');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'email format is RFC 5321 ASCII-only; non-ASCII domain must be rejected');
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

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function emailEdgeCasesProvider(): array
    {
        return [
            'empty string is rejected' => ['', false],
            'whitespace-only is rejected' => ['   ', false],
            'empty domain label "user@.com" is rejected' => ['user@.com', false],
            'IDN email "test@münchen.de" is rejected (RFC 6531 belongs to idn-email)' => ["test@m\u{FC}nchen.de", false],
            'IDN email "test@北京.cn" is rejected (RFC 6531 belongs to idn-email)' => ["test@北京.cn", false],
            'SMTPUTF8 email "用户@例子.广告" is rejected (RFC 6531 belongs to idn-email)' => ["\u{7528}\u{6237}@\u{4f8b}\u{5b50}.\u{5e7f}\u{544a}", false],
            'punycode domain "test@xn--mnchen-3ya.de" is valid' => ['test@xn--mnchen-3ya.de', true],
            'simple email "test@example.com" is valid' => ['test@example.com', true],
            'minimal email "a@b.co" is valid' => ['a@b.co', true],
            'email with subdomain is valid' => ['user@mail.example.com', true],
            'email with plus tag is valid' => ['user+tag@example.com', true],
            'email with dots is valid' => ['first.last@example.com', true],
            'email missing @ is rejected' => ['invalid-email', false],
            'email with empty domain "test@" is rejected' => ['test@', false],
            'single-label domain "test@com" is valid per RFC 5321' => ['test@com', true],
            'single-label domain "test@example" is valid per RFC 5321' => ['test@example', true],
            'non-ascii local is rejected per RFC 5321' => ["a\u{E9}@iana.org", false],
            'non-ascii domain is rejected per RFC 5321' => ["a@\u{E9}.org", false],
        ];
    }

    #[DataProvider('emailEdgeCasesProvider')]
    #[Test]
    public function email_edge_cases_match_expected_result(string $email, bool $expectedValid): void
    {
        if (!extension_loaded('intl') && 1 === preg_match('/[\x80-\xff]/', $email)) {
            self::markTestSkipped('ext-intl required for SMTPUTF8 (RFC 6531) validation');
        }

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
