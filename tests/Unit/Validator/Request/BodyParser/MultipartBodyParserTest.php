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
    public function parse_empty_multipart_body(): void
    {
        $body = '';
        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_whitespace_only_body(): void
    {
        $body = '   ';
        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_multipart_with_boundary(): void
    {
        $body = "boundary=boundary123\r\n\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('headers', $result[1]);
        $this->assertArrayHasKey('content', $result[1]);
        $this->assertSame("value\r\n", $result[1]['content']);
    }

    #[Test]
    public function parse_multipart_with_multiple_parts(): void
    {
        $body = "boundary=boundary123\r\n\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field1\"\r\n"
            . "\r\n"
            . "value1\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field2\"\r\n"
            . "\r\n"
            . "value2\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body);

        $this->assertCount(3, $result);
        $this->assertSame("value1\r\n", $result[1]['content']);
        $this->assertSame("value2\r\n", $result[2]['content']);
    }

    #[Test]
    public function parse_multipart_without_boundary(): void
    {
        $body = "Some random content without boundary";

        $result = $this->parser->parse($body);

        $this->assertSame([], $result);
    }

    #[Test]
    public function parse_multipart_with_headers_and_content(): void
    {
        $body = "boundary=boundary123\r\n\r\n"
            . "--boundary123\r\n"
            . "Content-Type: text/plain\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "content value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body);

        $this->assertCount(2, $result);
        $this->assertStringContainsString('Content-Type: text/plain', $result[1]['headers']);
        $this->assertStringContainsString('Content-Disposition: form-data; name="field"', $result[1]['headers']);
        $this->assertSame("content value\r\n", $result[1]['content']);
    }

    #[Test]
    public function parse_multipart_ignores_empty_sections(): void
    {
        $body = "boundary=boundary123\r\n\r\n"
            . "--boundary123\r\n"
            . "\r\n"
            . "\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body);

        $this->assertCount(2, $result);
        $this->assertSame("value\r\n", $result[1]['content']);
    }

    #[Test]
    public function parse_multipart_boundary_without_quotes_in_body(): void
    {
        $body = "boundary=boundary123\r\n\r\n"
            . "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--";

        $result = $this->parser->parse($body);

        $this->assertCount(2, $result);
        $this->assertSame("value\r\n", $result[1]['content']);
    }

    #[Test]
    public function parse_multipart_with_boundary_at_start(): void
    {
        $body = "--boundary123\r\n"
            . "Content-Disposition: form-data; name=\"field\"\r\n"
            . "\r\n"
            . "value\r\n"
            . "--boundary123--boundary=boundary123\r\n";

        $result = $this->parser->parse($body);

        $this->assertCount(1, $result);
        $this->assertSame("value\r\n", $result[0]['content']);
    }
}
