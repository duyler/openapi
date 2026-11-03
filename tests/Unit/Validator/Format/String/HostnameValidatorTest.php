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
            'IDN punycode label' => ['xn--mnchen-3ya.de'],
            'punycode pair' => ['xn--9n2bp8q.xn--9t4b11yi5a'],
            'pvalid exception punycode' => ['xn--zca29lwxobi7a'],
            'middle dot between l in punycode' => ['xn--ll-0ea'],
            'consecutive inner hyphens' => ['a--b.com'],
            'uppercase punycode body' => ['xn--NXASMQ6B'],
        ];
    }

    #[DataProvider('validHostnameValuesProvider')]
    #[Test]
    public function valid_hostname_values_pass(string $value): void
    {
        $this->validator->validate($value);

        $this->expectNotToPerformAssertions();
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
            'non-ascii umlaut' => ['münchen.de'],
            'idn label separator' => ['example．com'],
            'kelvin sign' => ["\u{212A}elvin.example.com"],
            'trailing newline' => ["example.com\n"],
            'invalid punycode' => ['xn--X'],
            'non-canonical punycode' => ['xn---9uc'],
            'punycode decoding to disallowed code point' => ['xn--7a'],
            'punycode decoding to all-ascii label' => ['xn--example-'],
            'punycode with hangul tone mark' => ['xn--07jt112bpxg.xn--9t4b11yi5a'],
            'punycode disallowed right-to-left exception' => ['xn--chb89f'],
            'punycode disallowed left-to-right exception' => ['xn--07jceefgh4c'],
            'punycode leading spacing mark' => ['xn--hello-txk'],
            'punycode leading nonspacing mark' => ['xn--hello-zed'],
            'punycode leading enclosing mark' => ['xn--hello-6bf'],
            'punycode middle dot without preceding l' => ['xn--al-0ea'],
            'punycode middle dot with nothing preceding' => ['xn--l-fda'],
            'punycode middle dot without following l' => ['xn--la-0ea'],
            'punycode middle dot with nothing following' => ['xn--l-gda'],
            'punycode greek keraia without greek follower' => ['xn--S-jib3p'],
            'punycode greek keraia without follower' => ['xn--wva3j'],
            'punycode hebrew geresh without hebrew' => ['xn--A-2hc5h'],
            'punycode katakana middle dot without han' => ['xn--defabc-k64e'],
            'punycode katakana middle dot alone' => ['xn--vek'],
            'punycode bidi rule violation' => ['xn--0ca24w'],
            'punycode double hyphen at third and fourth position' => ['XN--aa---o47jg78q'],
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
    public function non_ascii_value_is_rejected_per_rfc_1034(): void
    {
        $exception = null;

        try {
            $this->validator->validate('münchen.de');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('hostname', $exception->format);
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
        $this->assertStringContainsString('Invalid hostname format', $exception->getMessage());
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
