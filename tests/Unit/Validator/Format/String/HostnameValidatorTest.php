<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\HostnameValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[CoversClass(HostnameValidator::class)]
final class HostnameValidatorTest extends TestCase
{
    private HostnameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new HostnameValidator();
    }

    public static function validHostnameValuesProvider(): array
    {
        return [
            'simple domain' => ['example.com'],
            'localhost' => ['localhost'],
            'subdomain' => ['mail.example.com'],
            'deep subdomain' => ['sub.sub.example.com'],
            'www prefix' => ['www.example.com'],
            'numeric segments' => ['server123.example.com'],
            'hyphenated' => ['my-server.example.com'],
            'single letter domain' => ['a.com'],
            'numeric tld' => ['example.123'],
            'max length hostname' => [str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.com'],
            'IDN german umlaut' => ['münchen.de'],
            'IDN punycode pass-through' => ['xn--mnchen-3ya.de'],
        ];
    }

    #[DataProvider('validHostnameValuesProvider')]
    #[Test]
    public function valid_hostname_values_pass(string $value): void
    {
        $this->validator->validate($value);

        $this->assertTrue(true);
    }

    public static function invalidHostnameValuesProvider(): array
    {
        return [
            'underscore in name' => ['exam_ple.com'],
            'too long hostname' => [str_repeat('a', 254) . '.com'],
            'label too long' => [str_repeat('a', 64) . '.com'],
            'starts with hyphen' => ['-example.com'],
            'ends with hyphen' => ['example-.com'],
            'empty string' => [''],
            'single dot' => ['.'],
            'double dot' => ['example..com'],
            'spaces' => ['exam ple.com'],
            'special characters' => ['exam!ple.com'],
        ];
    }

    #[DataProvider('invalidHostnameValuesProvider')]
    #[Test]
    public function invalid_hostname_values_throw_exception(string $value): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($value);
    }

    #[Test]
    public function idn_hostname_passes_via_punycode_conversion(): void
    {
        $idnHostname = 'münchen.de';

        $exception = null;

        try {
            $this->validator->validate($idnHostname);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            'IDN hostname "münchen.de" must be accepted via idn_to_ascii punycode conversion per RFC 5891.',
        );
    }

    #[Test]
    public function leading_hyphen_hostname_rejected_with_descriptive_message(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid hostname format');

        $this->validator->validate('-bad.example.com');
    }

    #[Test]
    public function label_exceeding_63_chars_rejected(): void
    {
        $longLabel = str_repeat('a', 64) . '.com';

        $exception = null;

        try {
            $this->validator->validate($longLabel);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('hostname', $exception->format);
    }

    #[Test]
    public function hostname_exceeding_253_chars_rejected(): void
    {
        $longHostname = str_repeat('a', 250) . '.com';

        $exception = null;

        try {
            $this->validator->validate($longHostname);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('hostname', $exception->format);
        $this->assertStringContainsString('253', $exception->getMessage());
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');

        $this->validator->validate(123);
    }

    #[Test]
    public function throw_error_for_null(): void
    {
        $this->expectException(InvalidFormatException::class);

        $this->validator->validate(null);
    }

    #[Test]
    public function exception_contains_format_name(): void
    {
        try {
            $this->validator->validate('');
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame('hostname', $exception->format);
        }
    }

    #[Test]
    public function exception_contains_invalid_value(): void
    {
        $invalidValue = 'exam_ple.com';

        try {
            $this->validator->validate($invalidValue);
            $this->fail('Expected InvalidFormatException was not thrown');
        } catch (InvalidFormatException $exception) {
            $this->assertSame($invalidValue, $exception->value(reveal: true));
        }
    }
}
