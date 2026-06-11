<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class BodyParserParseTest extends TestCase
{
    private BodyParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BodyParser(
            jsonParser: new JsonBodyParser(),
            formParser: new FormBodyParser(),
            multipartParser: new MultipartBodyParser(),
            textParser: new TextBodyParser(),
            xmlParser: new XmlBodyParser(),
        );
    }

    #[Test]
    public function parse_json_returns_array(): void
    {
        $result = $this->parser->parse('{"key":"value"}', 'application/json');

        $this->assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function parse_form_urlencoded_returns_array(): void
    {
        $result = $this->parser->parse('name=test&value=1', 'application/x-www-form-urlencoded');

        $this->assertSame(['name' => 'test', 'value' => '1'], $result);
    }

    #[Test]
    public function parse_text_plain_returns_string(): void
    {
        $result = $this->parser->parse('hello world', 'text/plain');

        $this->assertSame('hello world', $result);
    }

    #[Test]
    public function parse_text_html_returns_string(): void
    {
        $result = $this->parser->parse('<p>hello</p>', 'text/html');

        $this->assertSame('<p>hello</p>', $result);
    }

    #[Test]
    public function parse_text_csv_returns_string(): void
    {
        $result = $this->parser->parse('a,b,c', 'text/csv');

        $this->assertSame('a,b,c', $result);
    }

    #[Test]
    public function parse_xml_returns_array(): void
    {
        $result = $this->parser->parse('<root><name>test</name></root>', 'application/xml');

        $this->assertSame(['name' => 'test'], $result);
    }

    #[Test]
    public function parse_text_xml_returns_array(): void
    {
        $result = $this->parser->parse('<root><name>test</name></root>', 'text/xml');

        $this->assertSame(['name' => 'test'], $result);
    }

    #[Test]
    public function parse_unknown_media_type_returns_raw_body(): void
    {
        $result = $this->parser->parse('raw data', 'application/octet-stream');

        $this->assertSame('raw data', $result);
    }

    #[Test]
    public function parse_multipart_returns_array(): void
    {
        $body = "--boundary\r\nContent-Disposition: form-data; name=\"field1\"\r\n\r\nvalue1\r\n--boundary--\r\n";
        $result = $this->parser->parse($body, 'multipart/form-data');

        $this->assertIsArray($result);
    }
}
