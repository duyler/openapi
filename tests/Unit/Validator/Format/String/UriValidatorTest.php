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
    public function triple_slash_authority_is_rejected(): void
    {
        $uri = 'http:///example.com';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->validator->validate($uri);
    }

    #[Test]
    public function port_above_max_is_rejected(): void
    {
        $uri = 'http://example.com:99999';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('URI port out of range');

        $this->validator->validate($uri);
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
    public function javascript_scheme_uri_is_rejected(): void
    {
        $uri = 'javascript:alert(1)';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->validator->validate($uri);
    }

    #[Test]
    public function relative_path_uri_is_rejected(): void
    {
        $uri = '/relative/path';

        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');

        $this->validator->validate($uri);
    }

    #[Test]
    public function unsupported_scheme_message_does_not_leak_scheme_name(): void
    {
        $uri = 'javascript://example.com/';

        $exception = null;

        try {
            $this->validator->validate($uri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertStringContainsString('Unsupported URI scheme', $exception->getMessage());
        $this->assertStringNotContainsString(
            'javascript',
            $exception->getMessage(),
            'getMessage() must not interpolate attacker-derived scheme (S-021).',
        );
    }

    /**
     * Defence in depth: regex allows non-ASCII hosts (IDN), but PHP's
     * filter_var(FILTER_VALIDATE_URL) on PHP 8.4+ rejects bare UTF-8 hosts
     * without Punycode conversion. IDN-to-ASCII conversion is explicitly
     * out of scope per the task spec. This test pins the current behaviour:
     * the regex layer accepts the host, but the final filter_var layer
     * rejects it. Tracked as escalation in
     * `.ai/tasks/review/19-format-validators-uri-uuid.md`.
     */
    #[Test]
    public function idn_host_uri_rejected_by_filter_var_layer(): void
    {
        $uri = 'https://' . "\xd0\xbf\xd1\x80\xd0\xb8\xd0\xbc\xd0\xb5\xd1\x80.\xd1\x80\xd1\x84";

        $exception = null;

        try {
            $this->validator->validate($uri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull(
            $exception,
            'IDN host URI is rejected by the filter_var defence-in-depth layer; '
            . 'see .ai/tasks/review/19-format-validators-uri-uuid.md for the spec contradiction.',
        );
    }

    /**
     * URI edge cases: scheme//authority requirement, allowlist, port range.
     *
     * The validator uses defence in depth (RFC 3986 regex → scheme allowlist
     * → port ≤ 65535 → filter_var). mailto/tel/data schemes lack `//authority`
     * and are therefore rejected by the regex layer even though they appear in
     * the scheme allowlist. file URIs require a non-empty host.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function uriEdgeCasesProvider(): array
    {
        return [
            'relative URI "../relative" is rejected (no scheme)' => ['../relative', false],
            'relative URI "path/to/file" is rejected (no scheme)' => ['path/to/file', false],
            'URN "urn:uuid:..." is rejected (no //authority, urn not in allowlist)' => ['urn:uuid:550e8400-e29b-41d4-a716-446655440000', false],
            'URN "urn:isbn:0451450523" is rejected' => ['urn:isbn:0451450523', false],
            'file URI "file:///path/to/file" is rejected (empty host)' => ['file:///path/to/file', false],
            'file URI with host "file://localhost/path" is accepted' => ['file://localhost/path/to/file', true],
            'mailto URI "mailto:test@test.com" is rejected (no //authority)' => ['mailto:test@test.com', false],
            'mailto URI with subject is rejected (no //authority)' => ['mailto:test@example.com?subject=Hello', false],
            'absolute https URI is valid (positive control)' => ['https://example.com', true],
            'absolute http URI with path is valid (positive control)' => ['http://example.com/path', true],
            'absolute ftp URI is valid' => ['ftp://example.com/file', true],
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

    #[Test]
    public function urn_uri_rejected_by_regex_layer(): void
    {
        $urnUri = 'urn:uuid:550e8400-e29b-41d4-a716-446655440000';

        $exception = null;

        try {
            $this->validator->validate($urnUri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception, 'URN must be rejected: no //authority and urn not in scheme allowlist');

        if (null !== $exception) {
            $this->assertSame('uri', $exception->format);
            $this->assertSame($urnUri, $exception->value(reveal: true));
        }
    }
}
