<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IdnEmailValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function sprintf;

#[CoversClass(IdnEmailValidator::class)]
final class IdnEmailValidatorTest extends TestCase
{
    private IdnEmailValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IdnEmailValidator();
    }

    public static function validIdnEmailValuesProvider(): array
    {
        return [
            'hangul local and domain' => ["\u{C2E4}\u{B840}@\u{C2E4}\u{B840}.\u{C14C}\u{C2A4}\u{C168}"],
            'ascii email' => ['joe.bloggs@example.com'],
            'non-ascii local with ascii domain' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@example.com"],
            'non-ascii quoted local part' => ["\"\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}\"@example.com"],
            'domain label not in unicode nfc' => ["user@cafe\u{301}.com"],
            'local part not in unicode nfc' => ["cafe\u{301}@example.com"],
            'c1 control in the local part' => ["\u{85}@example.com"],
            'noncharacter in the local part' => ["\u{FFFF}@example.com"],
            'local part at the 64-octet limit' => [str_repeat('a', 64) . '@example.com'],
            'supplementary-plane local part' => ["\u{1D54F}@example.com"],
            'quoted ascii local' => ['"test"@iana.org'],
            'quoted local with at sign' => ['"@"@iana.org'],
        ];
    }

    #[DataProvider('validIdnEmailValuesProvider')]
    #[Test]
    public function valid_idn_email_values_pass(string $value): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for SMTPUTF8 (RFC 6531) validation');
        }

        $this->validator->validate($value);

        $this->expectNotToPerformAssertions();
    }

    public static function invalidIdnEmailValuesProvider(): array
    {
        return [
            'integer instance' => [2962],
            'fullwidth commercial at is not a separator' => ["user\u{FF20}example.com"],
            'domain label starting with a hyphen' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@-example.com"],
            'domain label ending with a hyphen' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@example-.com"],
            'domain starting with a dot' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@.example.com"],
            'domain ending with a dot' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@example.com."],
            'two subsequent dots inside the domain' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@example..com"],
            'domain is required' => ["\u{3B4}\u{3BF}\u{3BA}\u{3B9}\u{3BC}\u{3AE}@"],
            'empty string' => [''],
            'missing at sign' => ['not-an-email'],
            'leading dot in local part' => ['.user@example.com'],
            'trailing dot in local part' => ['user.@example.com'],
            'two subsequent dots in local part' => ['us..er@example.com'],
            'local part longer than 64 octets' => [str_repeat('a', 65) . '@example.com'],
            'unclosed quoted local part' => ['"test@example.com'],
            'quoted local followed by text' => ['"test"test@example.com'],
            'dangling backslash in quoted local' => ['"test\\' . '"@example.com'],
            'quoted pair with non-ascii escape' => ["\"test\\\u{A9}\"@example.com"],
            'two at signs' => ['a@b@example.com'],
            'space in unquoted local part' => ['a b@example.com'],
        ];
    }

    #[DataProvider('invalidIdnEmailValuesProvider')]
    #[Test]
    public function invalid_idn_email_values_throw_exception(int|string $value): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for SMTPUTF8 (RFC 6531) validation');
        }

        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($value);
    }

    #[Test]
    public function idn_email_validator_accepts_unicode_email(): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for SMTPUTF8 (RFC 6531) validation');
        }

        $this->expectNotToPerformAssertions();

        $this->validator->validate("\u{7528}\u{6237}@\u{4f8b}\u{5b50}.\u{5e7f}\u{544a}");
        $this->validator->validate("test@m\u{FC}nchen.de");
        $this->validator->validate("test@北京.cn");
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
    public function idn_email_validator_rejects_email_exceeding_max_length(): void
    {
        $domain = str_repeat('b', 60) . '.' . str_repeat('c', 60) . '.' . str_repeat('d', 60) . '.' . str_repeat('e', 60) . '.' . str_repeat('f', 60) . '.com';
        $email = "user\u{E9}@" . $domain;

        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($email);
    }

    #[Test]
    public function idn_email_validator_rejects_quoted_local_failures(): void
    {
        $cases = [
            'dangling backslash at end of quoted string' => '"test' . '\\',
            'non-ascii character in a quoted pair' => "\"test\\\u{A9}\"@example.com",
        ];

        foreach ($cases as $value) {
            $exception = null;

            try {
                $this->validator->validate($value);
            } catch (InvalidFormatException $exception) {
            }

            $this->assertNotNull($exception, 'Malformed quoted local part must be rejected');
        }
    }

    #[Test]
    public function idn_email_validator_rejects_trailing_hyphen_label(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate("user@example\u{E9}-.com");
    }

    #[Test]
    public function idn_email_validator_accepts_hyphens_in_ascii_labels(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('joe-bloggs@example.com');
        $this->validator->validate('user@example-site.com');
    }

    #[Test]
    public function idn_email_validator_accepts_a_label_domain(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('user@xn--fiqs8s.example');
        $this->validator->validate('joe.bloggs@xn--fiqs8s.example');
    }

    #[Test]
    public function idn_email_validator_rejects_invalid_ascii_domain_characters(): void
    {
        $cases = [
            'underscore in domain label' => 'user@my_site.com',
            'colon in domain label' => 'user@foo:bar.com',
        ];

        foreach ($cases as $label => $value) {
            $exception = null;

            try {
                $this->validator->validate($value);
            } catch (InvalidFormatException $exception) {
            }

            $this->assertNotNull($exception, sprintf('%s must be rejected', $label));
            $this->assertSame('idn-email', $exception->format);
        }
    }

    #[Test]
    public function idn_email_validator_rejects_hyphen_only_edge_labels(): void
    {
        $cases = [
            'single hyphen label' => 'user@-.com',
            'all hyphens label' => 'user@a.--.com',
        ];

        foreach ($cases as $label => $value) {
            $exception = null;

            try {
                $this->validator->validate($value);
            } catch (InvalidFormatException $exception) {
            }

            $this->assertNotNull($exception, sprintf('%s must be rejected', $label));
            $this->assertSame('idn-email', $exception->format);
        }
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
