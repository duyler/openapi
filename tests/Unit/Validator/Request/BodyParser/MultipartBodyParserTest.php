<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class MultipartBodyParserTest extends TestCase
{
    private MultipartBodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new MultipartBodyParser();
    }

    #[Test]
    public function parse_empty_body_returns_empty_list(): void
    {
        $result = $this->parser->parse('', 'multipart/form-data; boundary=b123');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_whitespace_only_body_returns_empty_list(): void
    {
        $result = $this->parser->parse('   ', 'multipart/form-data; boundary=b123');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_boundary_unquoted_in_content_type_extracts_parts(): void
    {
        $contentType = 'multipart/form-data; boundary=boundary123';
        $body = "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('headers', $result[0]);
        $this->assertArrayHasKey('content', $result[0]);
        $this->assertSame("value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_boundary_quoted_in_content_type_extracts_parts(): void
    {
        $contentType = 'multipart/form-data; boundary="quotedBoundary"';
        $body = "--quotedBoundary\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--quotedBoundary--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertSame("value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_webkit_style_boundary_in_content_type_extracts_parts(): void
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundaryABC123';
        $body = "------WebKitFormBoundaryABC123\r\n"
            . "Content-Disposition: form-data; name=\"field1\"\r\n"
            . "\r\n"
            . "value1\r\n"
            . "------WebKitFormBoundaryABC123--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertSame("value1\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_multiple_parts_with_boundary_in_content_type(): void
    {
        $contentType = 'multipart/form-data; boundary=boundary123';
        $body = "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field1\"\r\n"
            . "\r\n"
            . "value1\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field2\"\r\n"
            . "\r\n"
            . "value2\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(2, $result);
        $this->assertSame("value1\r\n", $result[0]['content']);
        $this->assertSame("value2\r\n", $result[1]['content']);
    }

    #[Test]
    public function parse_part_with_multiple_headers(): void
    {
        $contentType = 'multipart/form-data; boundary=boundary123';
        $body = "--boundary123\r\n"
            . "Content-Type: text/plain\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "content value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Content-Type: text/plain', $result[0]['headers']);
        $this->assertStringContainsString('Content-Disposition: form-data; name="field"', $result[0]['headers']);
        $this->assertSame("content value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_ignores_empty_sections_between_boundaries(): void
    {
        $contentType = 'multipart/form-data; boundary=boundary123';
        $body = "--boundary123\r\n"
            . "\r\n"
            . "\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertSame("value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_falls_back_to_body_prefix_when_content_type_has_no_boundary(): void
    {
        $body = "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, 'multipart/form-data');

        $this->assertCount(1, $result);
        $this->assertSame("value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_falls_back_to_body_prefix_when_content_type_empty(): void
    {
        $body = "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, '');

        $this->assertCount(1, $result);
        $this->assertSame("value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_no_boundary_anywhere_returns_empty_list(): void
    {
        $body = 'Some random content without boundary prefix';

        $result = $this->parser->parse($body, 'multipart/form-data');

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_content_type_with_charset_then_boundary_extracts_boundary(): void
    {
        $contentType = 'multipart/form-data; charset=utf-8; boundary=boundary123';
        $body = "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertSame("value\r\n", $result[0]['content']);
    }

    #[Test]
    public function parse_crlf_terminated_final_boundary(): void
    {
        $contentType = 'multipart/form-data; boundary=b';
        $body = "--b\r\nContent-Disposition: form-data; name=\"f\"\r\n\r\nv\r\n--b--\r\n";

        $result = $this->parser->parse($body, $contentType);

        $this->assertCount(1, $result);
        $this->assertSame("v\r\n", $result[0]['content']);
    }
}
