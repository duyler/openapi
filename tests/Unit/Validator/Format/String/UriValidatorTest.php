<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UriValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(UriValidator::class)]
final class UriValidatorTest extends TestCase
{
    private UriValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UriValidator();
    }

    #[Test]
    public function valid_http_url(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com');
        $this->validator->validate('http://example.com/path');
    }

    #[Test]
    public function valid_https_url(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://example.com');
        $this->validator->validate('https://example.com/path?query=value');
    }

    #[Test]
    public function valid_ftp_url(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('ftp://example.com/file');
        $this->validator->validate('ftp://host/file');
    }

    #[Test]
    public function throw_error_for_invalid_url(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');
        $this->validator->validate('not-a-url');
    }

    #[Test]
    public function throw_error_for_missing_scheme(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('example.com');
    }

    #[Test]
    public function validate_with_query(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com?key=value');
    }

    #[Test]
    public function validate_with_fragment(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com#section');
    }

    #[Test]
    public function validate_with_port(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com:8080');
        $this->validator->validate('https://example.com:8443/path');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }

    #[Test]
    public function http_uri_with_port_path_query_fragment_passes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com:8080/path?q=1#frag');
    }

    #[Test]
    public function ftp_uri_with_host_and_path_passes(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('ftp://host/file');
    }

    #[Test]
    public function relative_path_uri_is_rejected(): void
    {
        $uri = '/relative/path';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->validator->validate($uri);
    }

    /**
     * RFC 3986 §3 allows an empty reg-name in the authority, so an
     * `http:///path` triple-slash URI is a syntactically valid URI
     * even though the host is empty. The validator no longer rejects
     * this shape; it accepts it per RFC 3986 generic syntax. The
     * previous rejection was a side effect of the now-removed
     * `filter_var(FILTER_VALIDATE_URL)` fallback.
     */
    #[Test]
    public function triple_slash_authority_is_valid_per_rfc_3986(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http:///example.com');
    }

    /**
     * RFC 3986 §3.3 `path-rootless = segment-nz-nc *( "/" segment )`
     * admits `(` and `)` via `sub-delims`, so `javascript:alert(1)`
     * is a syntactically valid URI. Per JSON Schema 2020-12 §7.3.1
     * the `uri` format validates RFC 3986 generic syntax only;
     * scheme-specific allowlisting is the application's responsibility
     * (handled at the consumer layer, not the format validator).
     */
    #[Test]
    public function javascript_scheme_uri_is_valid_per_rfc_3986(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('javascript:alert(1)');
    }

    /**
     * RFC 3986 host reg-name permits non-ASCII characters; the
     * validator accepts IDN hosts per RFC 3986 generic syntax. The
     * previous `filter_var(FILTER_VALIDATE_URL)` fallback rejected
     * bare UTF-8 hosts without Punycode conversion; that fallback
     * has been removed (Task 10, Variant A). IDN-to-ASCII Punycode
     * conversion remains explicitly out of scope.
     */
    #[Test]
    public function idn_host_uri_accepted_per_rfc_3986(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://' . "\xd0\xbf\xd1\x80\xd0\xb8\xd0\xbc\xd0\xb5\xd1\x80.\xd1\x80\xd1\x84");
    }

    #[Test]
    public function mailto_uri_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('mailto:user@example.com');
    }

    #[Test]
    public function mailto_uri_with_query_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('mailto:user@example.com?subject=Hello');
    }

    #[Test]
    public function tel_uri_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('tel:+1-816-555-1212');
    }

    #[Test]
    public function urn_uuid_uri_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('urn:uuid:550e8400-e29b-41d4-a716-446655440000');
    }

    #[Test]
    public function urn_isbn_uri_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('urn:isbn:0451450523');
    }

    #[Test]
    public function data_uri_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('data:text/plain;base64,SGVsbG8=');
    }

    #[Test]
    public function git_plus_https_scheme_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('git+https://github.com/foo/bar');
    }

    #[Test]
    public function ssh_scheme_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('ssh://host/path');
    }

    #[Test]
    public function irc_scheme_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('irc://irc.example.com/channel');
    }

    #[Test]
    public function magnet_scheme_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('magnet:?xt=urn:btih:abc123');
    }

    #[Test]
    public function bitcoin_scheme_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('bitcoin:1BgGZ9tcN4rm9KBzDn7KprQz7SZ2BXArAj');
    }

    #[Test]
    public function chrome_extension_scheme_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('chrome-extension://abc123/path');
    }

    #[Test]
    public function http_uri_with_port_in_path_is_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com:8080/path');
    }

    #[Test]
    public function relative_ref_without_scheme_is_rejected(): void
    {
        $uri = '//example.com/path';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->validator->validate($uri);
    }

    #[Test]
    public function uri_without_colon_is_rejected(): void
    {
        $uri = 'not a uri at all';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->validator->validate($uri);
    }

    /**
     * S-021: port-range error message must NOT interpolate the
     * attacker-supplied port value or any other attacker-derived
     * fragment (CWE-209).
     */
    #[Test]
    public function port_out_of_range_rejected_with_generic_message(): void
    {
        $uri = 'http://example.com:99999/path';

        $exception = null;

        try {
            $this->validator->validate($uri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'Port-out-of-range URI must be rejected');
        $this->assertSame('Invalid URI: port out of range', $exception->getMessage());
        $this->assertStringNotContainsString('99999', $exception->getMessage());
    }

    /**
     * S-021: even when the attacker controls the scheme AND the port,
     * the error message must not echo either back. The validator
     * must use a generic message verbatim.
     */
    #[Test]
    public function error_message_does_not_disclose_scheme_in_port_range_error(): void
    {
        $uri = 'javascript://example.com:99999/path';

        $exception = null;

        try {
            $this->validator->validate($uri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'Out-of-range port must trigger InvalidFormatException');
        $this->assertSame('Invalid URI: port out of range', $exception->getMessage());
        $this->assertStringNotContainsString(
            'javascript',
            $exception->getMessage(),
            'getMessage() must not disclose attacker-supplied scheme (S-021, CWE-209).',
        );
        $this->assertStringNotContainsString('99999', $exception->getMessage());
    }

    /**
     * URI edge cases across all four RFC 3986 §3 hier-part forms.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function uriEdgeCasesProvider(): array
    {
        return [
            'relative URI "../relative" is rejected (no scheme)' => ['../relative', false],
            'relative URI "path/to/file" is rejected (no scheme)' => ['path/to/file', false],
            'relative network-path "//example.com/path" is rejected (no scheme)' => ['//example.com/path', false],
            'URN uuid is valid (path-rootless)' => ['urn:uuid:550e8400-e29b-41d4-a716-446655440000', true],
            'URN isbn is valid (path-rootless)' => ['urn:isbn:0451450523', true],
            'file URI "file:///path/to/file" is valid (empty host allowed)' => ['file:///path/to/file', true],
            'file URI with host is valid' => ['file://localhost/path/to/file', true],
            'mailto URI is valid (path-rootless)' => ['mailto:test@test.com', true],
            'mailto URI with subject is valid (path-rootless + query)' => ['mailto:test@example.com?subject=Hello', true],
            'tel URI is valid (path-rootless)' => ['tel:+1234567890', true],
            'data URI is valid (path-rootless)' => ['data:text/plain;base64,SGVsbG8=', true],
            'git+https URI is valid (scheme allows "+")' => ['git+https://github.com/foo/bar', true],
            'ssh URI is valid' => ['ssh://host/path', true],
            'magnet URI is valid (path-empty)' => ['magnet:?xt=urn:btih:abc', true],
            'bitcoin URI is valid (path-rootless)' => ['bitcoin:1BgGZ9tcN4rm9KBzDn7KprQz7SZ2BXArAj', true],
            'javascript URI is valid per RFC 3986' => ['javascript:alert(1)', true],
            'absolute https URI is valid (positive control)' => ['https://example.com', true],
            'absolute http URI with path is valid (positive control)' => ['http://example.com/path', true],
            'absolute ftp URI is valid' => ['ftp://example.com/file', true],
            'IPv6 literal host is valid (regex host branch \[[0-9a-fA-F:]+\])' => ['http://[::1]:8080/path', true],
            'port 0 is valid per RFC 3986 (DIGIT allows zero)' => ['http://example.com:0/', true],
            'port 65535 is valid (MAX_PORT boundary)' => ['http://example.com:65535/', true],
            'port 65536 is rejected (first value above MAX_PORT)' => ['http://example.com:65536/', false],
            'URI "http://example.com:/path" is valid via path-absolute fallback (regex absorbs //example.com:/path as path-absolute hier-part, not authority+empty-port)' => ['http://example.com:/path', true],
        ];
    }

    #[DataProvider('uriEdgeCasesProvider')]
    #[Test]
    public function uri_edge_cases_match_expected_result(string $uri, bool $expectedValid): void
    {
        $exception = null;

        try {
            $this->validator->validate($uri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertSame(
            $expectedValid,
            null === $exception,
            sprintf(
                'URI "%s" was expected to be %s but is %s',
                $uri,
                $expectedValid ? 'valid' : 'invalid',
                null === $exception ? 'valid' : 'invalid: ' . $exception->getMessage(),
            ),
        );
    }

    #[Test]
    public function relative_uri_rejection_carries_format_name_and_value(): void
    {
        $relativeUri = '../relative';

        $exception = null;

        try {
            $this->validator->validate($relativeUri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'Relative URI must be rejected');

        if (null !== $exception) {
            $this->assertSame('uri', $exception->format);
            $this->assertSame($relativeUri, $exception->value(reveal: true));
        }
    }
}
