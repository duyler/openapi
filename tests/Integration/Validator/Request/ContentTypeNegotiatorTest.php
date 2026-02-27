<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Request;

use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ContentTypeNegotiatorTest extends TestCase
{
    private ContentTypeNegotiator $negotiator;

    protected function setUp(): void
    {
        $this->negotiator = new ContentTypeNegotiator();
    }

    #[Test]
    public function get_media_type_simple(): void
    {
        $contentType = 'application/json';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_charset(): void
    {
        $contentType = 'application/json; charset=utf-8';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_multiple_parameters(): void
    {
        $contentType = 'multipart/form-data; boundary=boundary123; charset=utf-8';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('multipart/form-data', $result);
    }

    #[Test]
    public function get_media_type_with_whitespace(): void
    {
        $contentType = ' application/json ';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_boundary(): void
    {
        $contentType = 'multipart/form-data; boundary=----WebKitFormBoundary7MA4YWxkTrZu0gW';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('multipart/form-data', $result);
    }

    #[Test]
    public function get_media_type_text_plain(): void
    {
        $contentType = 'text/plain';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('text/plain', $result);
    }

    #[Test]
    public function get_media_type_application_xml(): void
    {
        $contentType = 'application/xml; charset=utf-8';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/xml', $result);
    }

    #[Test]
    public function get_media_type_text_html(): void
    {
        $contentType = 'text/html; charset=ISO-8859-1';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('text/html', $result);
    }

    #[Test]
    public function get_charset_from_content_type(): void
    {
        $contentType = 'application/json; charset=utf-8';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertSame('utf-8', $result);
    }

    #[Test]
    public function get_charset_uppercase(): void
    {
        $contentType = 'application/json; charset=UTF-8';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertSame('UTF-8', $result);
    }

    #[Test]
    public function get_charset_with_multiple_parameters(): void
    {
        $contentType = 'multipart/form-data; boundary=boundary123; charset=utf-8';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertSame('utf-8', $result);
    }

    #[Test]
    public function get_charset_without_charset_returns_null(): void
    {
        $contentType = 'application/json';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertNull($result);
    }

    #[Test]
    public function get_charset_with_other_parameters(): void
    {
        $contentType = 'application/json; boundary=boundary123';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertNull($result);
    }

    #[Test]
    public function get_charset_iso_8859_1(): void
    {
        $contentType = 'text/html; charset=ISO-8859-1';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertSame('ISO-8859-1', $result);
    }

    #[Test]
    public function get_charset_windows_1252(): void
    {
        $contentType = 'text/html; charset=windows-1252';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertSame('windows-1252', $result);
    }

    #[Test]
    public function get_charset_empty_value_returns_null(): void
    {
        $contentType = 'application/json; charset=';
        $result = $this->negotiator->getCharset($contentType);

        $this->assertNull($result);
    }
}
