<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Format\String\IdnProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

#[CoversClass(IdnProcessor::class)]
final class IdnProcessorTest extends TestCase
{
    private IdnProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new IdnProcessor();
    }

    public static function asciiHostnameProvider(): array
    {
        return [
            'simple domain' => ['example.com', true],
            'single label' => ['hostname', true],
            'label starting with digit' => ['1host', true],
            'consecutive inner hyphens' => ['a--b.com', true],
            'uppercase punycode body' => ['xn--NXASMQ6B', true],
            'punycode pair' => ['xn--9n2bp8q.xn--9t4b11yi5a', true],
            'pvalid exception punycode' => ['xn--zca29lwxobi7a', true],
            'middle dot between l in punycode' => ['xn--ll-0ea', true],
            'max label length' => [str_repeat('a', 63) . '.com', true],
            'max domain length' => [
                str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 57) . '.com',
                true,
            ],
            'idn label separator rejected' => ['example．com', false],
            'kelvin sign rejected' => ["\u{212A}elvin.example.com", false],
            'trailing newline rejected' => ["example.com\n", false],
            'leading space rejected' => [' example.com', false],
            'underscore rejected' => ['exam_ple.com', false],
            'empty label rejected' => ['example..com', false],
            'label too long rejected' => [str_repeat('a', 64) . '.com', false],
            'domain too long rejected' => [
                str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 58) . '.com',
                false,
            ],
            'invalid punycode rejected' => ['xn--X', false],
            'non-canonical punycode rejected' => ['xn---9uc', false],
            'punycode decoding to disallowed code point rejected' => ['xn--7a', false],
            'punycode decoding to all-ascii label rejected' => ['xn--example-', false],
            'punycode hangul tone mark rejected' => ['xn--07jt112bpxg.xn--9t4b11yi5a', false],
            'punycode disallowed right-to-left exception rejected' => ['xn--chb89f', false],
            'punycode disallowed left-to-right exception rejected' => ['xn--07jceefgh4c', false],
            'punycode leading spacing mark rejected' => ['xn--hello-txk', false],
            'punycode leading nonspacing mark rejected' => ['xn--hello-zed', false],
            'punycode leading enclosing mark rejected' => ['xn--hello-6bf', false],
            'punycode middle dot without preceding l rejected' => ['xn--al-0ea', false],
            'punycode middle dot with nothing preceding rejected' => ['xn--l-fda', false],
            'punycode middle dot without following l rejected' => ['xn--la-0ea', false],
            'punycode middle dot with nothing following rejected' => ['xn--l-gda', false],
            'punycode greek keraia without greek follower rejected' => ['xn--S-jib3p', false],
            'punycode greek keraia without follower rejected' => ['xn--wva3j', false],
            'punycode hebrew geresh without hebrew rejected' => ['xn--A-2hc5h', false],
            'punycode hebrew gershayim without hebrew rejected' => ['xn--A-2hc8h', false],
            'punycode katakana middle dot without han rejected' => ['xn--defabc-k64e', false],
            'punycode katakana middle dot alone rejected' => ['xn--vek', false],
            'punycode bidi rule violation rejected' => ['xn--0ca24w', false],
            'punycode double hyphen at third and fourth position rejected' => ['XN--aa---o47jg78q', false],
            'punycode with zero width joiner after virama accepted' => ['xn--11b2ezcw70k', true],
            'empty string rejected' => ['', false],
        ];
    }

    #[DataProvider('asciiHostnameProvider')]
    #[Test]
    public function ascii_hostname_validation(string $domain, bool $expected): void
    {
        self::assertSame($expected, $this->processor->isAsciiHostname($domain));
    }

    public static function internationalizedHostnameProvider(): array
    {
        return [
            'ascii domain accepted' => ['example.com', true],
            'punycode label accepted' => ['xn--mnchen-3ya.de', true],
            'hangul hostname accepted' => ["\u{C2E4}\u{B840}.\u{C14C}\u{C2A4}\u{C168}", true],
            'german umlaut accepted' => ["m\u{FC}nchen.de", true],
            'fullwidth digits mapped' => ["\u{FF11}\u{FF12}\u{FF13}", true],
            'non-nfc u-label normalized' => ["cafe\u{301}.com", true],
            'ideographic full stop separator' => ["a\u{3002}b", true],
            'fullwidth full stop separator' => ["a\u{FF0E}b", true],
            'halfwidth ideographic full stop separator' => ["a\u{FF61}b", true],
            'middle dot between l accepted' => ["l\u{B7}l", true],
            'greek keraia followed by greek accepted' => ["\u{3B1}\u{375}\u{3B2}", true],
            'hebrew geresh preceded by hebrew accepted' => ["\u{5D0}\u{5F3}\u{5D1}", true],
            'hebrew gershayim preceded by hebrew accepted' => ["\u{5D0}\u{5F4}\u{5D1}", true],
            'katakana middle dot with hiragana accepted' => ["\u{30FB}\u{3042}", true],
            'katakana middle dot with han accepted' => ["\u{30FB}\u{4E01}", true],
            'arabic-indic digits unmixed accepted' => ["\u{628}\u{660}\u{628}", true],
            'extended arabic-indic digits unmixed accepted' => ["\u{6F0}0", true],
            'zwj preceded by virama accepted' => ["\u{915}\u{94D}\u{200D}\u{937}", true],
            'zwnj preceded by virama accepted' => ["\u{915}\u{94D}\u{200C}\u{937}", true],
            'pvalid exceptions accepted' => ["\u{DF}\u{3C2}", true],
            'ace prefix produced by mapping accepted' => ["\u{FF58}\u{FF4E}--nxasmq6b", true],
            'hangul tone mark first char rejected' => ["\u{302E}\u{C2E4}\u{B840}.\u{C14C}\u{C2A4}\u{C168}", false],
            'hangul tone mark inside label rejected' => ["\u{C2E4}\u{302E}\u{B840}.\u{C14C}\u{C2A4}\u{C168}", false],
            'disallowed right-to-left exceptions rejected' => ["\u{640}\u{7FA}", false],
            'disallowed left-to-right exceptions rejected' => ["\u{3031}\u{3032}\u{3033}\u{3034}\u{3035}\u{302E}\u{302F}\u{303B}", false],
            'middle dot without preceding l rejected' => ["a\u{B7}l", false],
            'middle dot with nothing preceding rejected' => ["\u{B7}l", false],
            'middle dot without following l rejected' => ["l\u{B7}a", false],
            'middle dot with nothing following rejected' => ["l\u{B7}", false],
            'greek keraia not followed by greek rejected' => ["\u{3B1}\u{375}S", false],
            'greek keraia with nothing following rejected' => ["\u{3B1}\u{375}", false],
            'hebrew geresh not preceded by hebrew rejected' => ["A\u{5F3}\u{5D1}", false],
            'hebrew geresh with nothing preceding rejected' => ["\u{5F3}\u{5D1}", false],
            'hebrew gershayim not preceded by hebrew rejected' => ["A\u{5F4}\u{5D1}", false],
            'hebrew gershayim with nothing preceding rejected' => ["\u{5F4}\u{5D1}", false],
            'katakana middle dot without cjk context rejected' => ["def\u{30FB}abc", false],
            'katakana middle dot alone rejected' => ["\u{30FB}", false],
            'mixed arabic-indic digit blocks rejected' => ["\u{628}\u{660}\u{6F0}", false],
            'label of only arabic-indic digits rejected' => ["\u{660}\u{661}", false],
            'zwj not preceded by virama rejected' => ["\u{915}\u{200D}\u{937}", false],
            'zwj with nothing preceding rejected' => ["\u{200D}\u{937}", false],
            'zwnj without virama at every occurrence rejected' => ["\u{915}\u{94D}\u{937}x\u{200C}y", false],
            'bidi digit-first label rejected' => ["0a.\u{5D0}", false],
            'digit before right-to-left letter rejected' => ["0\u{627}", false],
            'left-to-right label with right-to-left letter rejected' => ["a\u{5D0}", false],
            'right-to-left label mixing digit types rejected' => ["\u{5D0}0\u{660}", false],
            'invalid punycode rejected' => ['xn--X', false],
            'punycode decoding to disallowed code point rejected' => ['xn--7a', false],
            'u-label with double hyphen at third and fourth position rejected' => ['XN--aa---o47jg78q', false],
            'punycode bidi rule violation rejected' => ['xn--0ca24w', false],
            'a-label longer than 63 octets after mapping rejected' => [str_repeat("\u{FC}", 60), false],
            'u-label 64 chars rejected' => [str_repeat('a', 64), false],
            'empty label between dots rejected' => ['a..b', false],
            'leading dot rejected' => ['.example', false],
            'single dot rejected' => ['.', false],
            'control character rejected' => ["a\nb", false],
            'invalid utf-8 byte sequence rejected' => ["\xFF", false],
            'right-to-left label with left-to-right letter rejected' => ["\u{5D0}abc", false],
            'empty string rejected' => ['', false],
        ];
    }

    #[DataProvider('internationalizedHostnameProvider')]
    #[Test]
    public function internationalized_hostname_validation(string|array $domain, ?bool $expected = null): void
    {
        if (false === extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for IDNA hostname validation');
        }

        $expectedValid = $expected ?? false;

        self::assertSame($expectedValid, $this->processor->isInternationalizedHostname($domain));
    }
}
