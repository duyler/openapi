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

    /**
     * URI edge cases: relative references, URN, file URI, mailto URI.
     *
     * The validator uses PHP's filter_var with FILTER_VALIDATE_URL. This
     * function accepts certain non-http(s) URIs (file://, mailto:) but rejects
     * relative references and URN URIs (urn:uuid:...). This is a
     * characterization of the underlying filter_var behavior — the validator
     * does not implement strict RFC 3986 URI validation.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function uriEdgeCasesProvider(): array
    {
        return [
            'relative URI "../relative" is rejected (no scheme)' => ['../relative', false],
            'relative URI "path/to/file" is rejected (no scheme)' => ['path/to/file', false],
            'URN "urn:uuid:550e8400-e29b-41d4-a716-446655440000" is rejected (filter_var does not support URN scheme)' => ['urn:uuid:550e8400-e29b-41d4-a716-446655440000', false],
            'URN "urn:isbn:0451450523" is rejected' => ['urn:isbn:0451450523', false],
            'file URI "file:///path/to/file" is accepted by filter_var' => ['file:///path/to/file', true],
            'file URI with host "file://localhost/path" is accepted' => ['file://localhost/path/to/file', true],
            'mailto URI "mailto:test@test.com" is accepted by filter_var' => ['mailto:test@test.com', true],
            'mailto URI with subject is accepted' => ['mailto:test@example.com?subject=Hello', true],
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

        $this->assertNotNull($exception, 'Relative URI must be rejected by filter_var-based validator');

        if (null !== $exception) {
            $this->assertSame('uri', $exception->format);
            $this->assertSame($relativeUri, $exception->value);
        }
    }

    #[Test]
    public function urn_uri_rejection_documents_filter_var_limitation(): void
    {
        $urnUri = 'urn:uuid:550e8400-e29b-41d4-a716-446655440000';

        $exception = null;

        try {
            $this->validator->validate($urnUri);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull(
            $exception,
            'URN must be rejected by current filter_var-based validator. '
            . 'If this assertion fails, the validator has been updated to support URN URIs.',
        );

        if (null !== $exception) {
            $this->assertSame('uri', $exception->format);
            $this->assertSame($urnUri, $exception->value);
        }
    }
}
