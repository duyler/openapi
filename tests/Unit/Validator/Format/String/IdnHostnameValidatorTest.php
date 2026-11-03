<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IdnHostnameValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;
use function str_repeat;

#[CoversClass(IdnHostnameValidator::class)]
final class IdnHostnameValidatorTest extends TestCase
{
    private IdnHostnameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IdnHostnameValidator();
    }

    public static function validIdnHostnameValuesProvider(): array
    {
        return [
            'ascii domain' => ['example.com'],
            'multi label ascii' => ['sub.example.co.uk'],
            'punycode label' => ['xn--mnchen-3ya.de'],
            'unicode hostname' => ['例え.テスト'],
            'german umlaut' => ["m\u{FC}nchen.de"],
            'chinese' => ['北京.cn'],
            'hangul hostname' => ["\u{C2E4}\u{B840}.\u{C14C}\u{C2A4}\u{C168}"],
            'fullwidth digits mapped' => ["\u{FF11}\u{FF12}\u{FF13}"],
            'non-nfc u-label normalized by uts 46' => ["cafe\u{301}.com"],
            'ideographic full stop separator' => ["a\u{3002}b"],
            'fullwidth full stop separator' => ["a\u{FF0E}b"],
            'halfwidth ideographic full stop separator' => ["a\u{FF61}b"],
            'middle dot between l' => ["l\u{B7}l"],
            'greek keraia followed by greek' => ["\u{3B1}\u{375}\u{3B2}"],
            'hebrew geresh preceded by hebrew' => ["\u{5D0}\u{5F3}\u{5D1}"],
            'hebrew gershayim preceded by hebrew' => ["\u{5D0}\u{5F4}\u{5D1}"],
            'katakana middle dot with hiragana' => ["\u{30FB}\u{3042}"],
            'katakana middle dot with katakana' => ["\u{30FB}\u{30AB}"],
            'katakana middle dot with han' => ["\u{30FB}\u{4E01}"],
            'arabic-indic digits unmixed' => ["\u{628}\u{660}\u{628}"],
            'extended arabic-indic digits unmixed' => ["\u{6F0}0"],
            'zwj preceded by virama' => ["\u{915}\u{94D}\u{200D}\u{937}"],
            'zwnj preceded by virama' => ["\u{915}\u{94D}\u{200C}\u{937}"],
            'pvalid exceptions' => ["\u{DF}\u{3C2}"],
            'uppercase punycode body' => ['xn--NXASMQ6B'],
            'ace prefix produced by mapping' => ["\u{FF58}\u{FF4E}--nxasmq6b"],
        ];
    }

    #[DataProvider('validIdnHostnameValuesProvider')]
    #[Test]
    public function valid_idn_hostname_values_pass(string $value): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for IDNA hostname validation');
        }

        $this->validator->validate($value);

        $this->expectNotToPerformAssertions();
    }

    public static function invalidIdnHostnameValuesProvider(): array
    {
        return [
            'empty string' => [''],
            'spaces inside label' => ['not a hostname'],
            'leading and trailing hyphen label' => ['-invalid-.hostname'],
            'trailing newline' => ["example.com\n"],
            'leading dot' => ['.example'],
            'trailing dot' => ['example.'],
            'empty label between dots' => ['a..b'],
            'single dot' => ['.'],
            'single ideographic full stop' => ["\u{3002}"],
            'hangul single dot tone mark first char' => ["\u{302E}\u{C2E4}\u{B840}.\u{C14C}\u{C2A4}\u{C168}"],
            'hangul tone mark inside label' => ["\u{C2E4}\u{302E}\u{B840}.\u{C14C}\u{C2A4}\u{C168}"],
            'disallowed right-to-left exceptions' => ["\u{640}\u{7FA}"],
            'disallowed left-to-right exceptions' => ["\u{3031}\u{3032}\u{3033}\u{3034}\u{3035}\u{302E}\u{302F}\u{303B}"],
            'middle dot without preceding l' => ["a\u{B7}l"],
            'middle dot with nothing preceding' => ["\u{B7}l"],
            'middle dot without following l' => ["l\u{B7}a"],
            'middle dot with nothing following' => ["l\u{B7}"],
            'greek keraia not followed by greek' => ["\u{3B1}\u{375}S"],
            'greek keraia with nothing following' => ["\u{3B1}\u{375}"],
            'hebrew geresh not preceded by hebrew' => ["A\u{5F3}\u{5D1}"],
            'hebrew geresh with nothing preceding' => ["\u{5F3}\u{5D1}"],
            'hebrew gershayim not preceded by hebrew' => ["A\u{5F4}\u{5D1}"],
            'hebrew gershayim with nothing preceding' => ["\u{5F4}\u{5D1}"],
            'katakana middle dot without cjk context' => ["def\u{30FB}abc"],
            'katakana middle dot alone' => ["\u{30FB}"],
            'mixed arabic-indic digit blocks' => ["\u{628}\u{660}\u{6F0}"],
            'label of only arabic-indic digits' => ["\u{660}\u{661}"],
            'zwj not preceded by virama' => ["\u{915}\u{200D}\u{937}"],
            'zwj with nothing preceding' => ["\u{200D}\u{937}"],
            'zwnj without virama at every occurrence' => ["\u{915}\u{94D}\u{937}x\u{200C}y"],
            'bidi digit-first label' => ["0a.\u{5D0}"],
            'digit before right-to-left letter' => ["0\u{627}"],
            'left-to-right label with right-to-left letter' => ["a\u{5D0}"],
            'right-to-left label mixing digit types' => ["\u{5D0}0\u{660}"],
            'invalid punycode' => ['xn--X'],
            'non-canonical punycode' => ['xn---9uc'],
            'punycode decoding to disallowed code point' => ['xn--7a'],
            'punycode decoding to all-ascii label' => ['xn--example-'],
            'u-label with double hyphen at third and fourth position' => ['XN--aa---o47jg78q'],
            'punycode bidi rule violation' => ['xn--0ca24w'],
            'a-label longer than 63 octets after mapping' => [str_repeat("\u{FC}", 60)],
            'u-label 64 chars' => [str_repeat('a', 64)],
        ];
    }

    #[DataProvider('invalidIdnHostnameValuesProvider')]
    #[Test]
    public function invalid_idn_hostname_values_throw_exception(string $value): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for IDNA hostname validation');
        }

        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($value);
    }

    #[Test]
    public function idn_hostname_validator_accepts_unicode_hostname(): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for IDNA hostname validation');
        }

        $this->expectNotToPerformAssertions();
        $this->validator->validate('例え.テスト');
        $this->validator->validate("m\u{FC}nchen.de");
        $this->validator->validate('北京.cn');
    }

    #[Test]
    public function idn_hostname_validator_accepts_ascii_hostname(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('example.com');
        $this->validator->validate('sub.example.co.uk');
        $this->validator->validate('xn--mnchen-3ya.de');
    }

    #[Test]
    public function idn_hostname_validator_rejects_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not a hostname');
    }

    #[Test]
    public function idn_hostname_validator_rejects_empty_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('');
    }

    #[Test]
    public function idn_hostname_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate('-invalid-.hostname');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('idn-hostname', $exception->format);
    }

    #[Test]
    public function idn_hostname_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(['array']);
    }
}
