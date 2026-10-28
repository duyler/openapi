<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Format\String\UriGrammar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriGrammar::class)]
final class UriGrammarTest extends TestCase
{
    private UriGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new UriGrammar();
    }

    public static function absoluteUriProvider(): array
    {
        return [
            'plain http uri' => ['http://example.com', false, true],
            'uri with port path query fragment' => ['http://foo.bar:8080/a/b?baz=qux#quux', false, true],
            'ldap uri with ipv6 host and triple query' => ['ldap://[2001:db8::7]/c=GB?objectClass?one', false, true],
            'mailto rootless path with at sign' => ['mailto:John.Doe@example.com', false, true],
            'urn with colons in rootless path' => ['urn:uuid:550e8400-e29b-41d4-a716-446655440000', false, true],
            'scheme with empty hier part' => ['about:blank', false, true],
            'file uri with empty host' => ['file:///path/to/file', false, true],
            'percent-encoded space in query' => ['http://foo.bar/?q=Test%20URL-encoded%20stuff', false, true],
            'parentheses in path and fragment' => ['http://foo.com/blah_(wikipedia)_blah#cite-1', false, true],
            'empty port after authority' => ['http://example.com:/path', false, true],
            'iri with unicode host' => ['http://ƒøø.ßår/', true, true],
            'iri query with unicode' => ['http://ƒøø.ßår/?∂éœ=πîx#πîüx', true, true],
            'iri supplementary private use in query' => ["http://ƒøø.ßår/?q=\u{F0000}", true, true],
            'iri treated as invalid in uri mode' => ['http://ƒøø.ßår/', false, false],
            'trailing newline rejected' => ["http://foo.bar/\n", false, false],
            'space in path rejected' => ['http://foo.bar/a b', false, false],
            'backslash in path rejected' => ['https://example.org/foobar\\.txt', false, false],
            'double quote in path rejected' => ['https://example.org/foobar".txt', false, false],
            'angle brackets in path rejected' => ['https://example.org/foobar<>.txt', false, false],
            'curly braces in path rejected' => ['https://example.org/foobar{}.txt', false, false],
            'caret pipe grave rejected' => ['https://example.org/foobar^`.txt', false, false],
            'lone percent rejected' => ['http://example.com/%', false, false],
            'incomplete percent triplet rejected' => ['http://example.com/%A', false, false],
            'non-hex percent rejected' => ['http://example.com/%zz', false, false],
            'at sign in bracketed userinfo rejected' => ['https://[@example.org/test.txt', false, false],
            'double at in authority rejected' => ['https://a@b@example.com/', false, false],
            'non-numeric port rejected' => ['http://example.com:abc/path', false, false],
            'port above maximum rejected' => ['http://example.com:65536/', false, false],
            'ipv6 without brackets rejected' => ['http://2001:0db8:85a3:0000:0000:8a2e:0370:7334', false, false],
            'embedded ipv4 leading zero rejected' => ['http://[::ffff:01.2.3.4]', false, false],
            'unclosed bracket rejected' => ['http://[::1/path', false, false],
            'garbage after bracket rejected' => ['http://[::1]x', false, false],
            'empty ip literal rejected' => ['http://[]/', false, false],
            'brackets in path rejected' => ['http:/[::1]', false, false],
            'no scheme rejected' => ['//example.com/path', false, false],
            'colon in first rootless segment is allowed' => ['foo:1:b', false, true],
            'ipv future literal is valid' => ['http://[v7.future]/', false, true],
            'space in authority rejected' => ['http://foo bar/', false, false],
            'empty hier part is valid' => ['mailto:', false, true],
            'bracket in reg-name rejected' => ['http://ex[ample.com/', false, false],
            'iri unicode rootless path accepted' => ['http://ƒøø.ßår/πî', true, true],
            'iri unicode fragment accepted' => ['http://ƒøø.ßår/p#πî', true, true],
            'space in relative reference rejected' => ['a b', false, false],
            'query with angle bracket rejected' => ['/p?a<b', false, false],
        ];
    }

    #[DataProvider('absoluteUriProvider')]
    #[Test]
    public function absolute_uri_grammar(string $value, bool $allowUcsChar, bool $expected): void
    {
        self::assertSame($expected, $this->grammar->isAbsoluteUri($value, $allowUcsChar));
    }

    public static function uriReferenceProvider(): array
    {
        return [
            'empty reference is valid' => ['', false, true],
            'network path reference' => ['//example.com/path', false, true],
            'absolute path reference' => ['/path/to/resource', false, true],
            'relative reference' => ['path/to/resource', false, true],
            'colon in first relative segment rejected' => ['1:b', false, false],
            'leading colon rejected' => [':b', false, false],
            'single slash is valid' => ['/', false, true],
            'double slash authority only is valid' => ['//', false, true],
            'relative first segment without colon allows inner colon' => ['a/b:c', false, true],
            'rootless single segment valid' => ['segment', false, true],
            'trailing line feed rejected' => ["/p\n", false, false],
            'backslash path rejected' => ['\\\\WINDOWS\\fileshare', false, false],
            'fragment with backslash rejected' => ['#frag\\ment', false, false],
            'fragment only valid' => ['#fragment', false, true],
            'query only valid' => ['?query=1', false, true],
            'percent-encoded userinfo is valid' => ['//user:p%40ss@example.com:8080/path?q=1#frag', false, true],
            'iri reference with unicode path' => ['/âππ', true, true],
            'iri protocol relative with query and fragment' => ['//ƒøø.ßår/?∂éœ=πîx#πîüx', true, true],
            'non-ascii rejected in uri mode' => ['/foobar®.txt', false, false],
            'space in relative reference rejected' => ['a b', false, false],
            'query with angle bracket rejected' => ['/p?a<b', false, false],
        ];
    }

    #[DataProvider('uriReferenceProvider')]
    #[Test]
    public function uri_reference_grammar(string $value, bool $allowUcsChar, bool $expected): void
    {
        self::assertSame($expected, $this->grammar->isUriReference($value, $allowUcsChar));
    }

    #[Test]
    public function query_allows_iprivate_only_in_iri_mode(): void
    {
        $value = 'http://example.com/?q=' . "\u{E000}";

        self::assertTrue($this->grammar->isAbsoluteUri($value, true));
        self::assertFalse($this->grammar->isAbsoluteUri($value, false));
    }
}
