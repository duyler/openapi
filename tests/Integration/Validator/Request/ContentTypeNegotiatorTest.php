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
}
