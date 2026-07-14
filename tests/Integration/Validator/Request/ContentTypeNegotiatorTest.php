<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Request;

use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
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
    public function get_media_type_without_q_value_is_accepted(): void
    {
        $contentType = 'application/json';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_q_value_one_is_accepted(): void
    {
        $contentType = 'application/json; q=1';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_q_value_decimal_one_is_accepted(): void
    {
        $contentType = 'application/json; q=1.0';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_q_value_zero_nine_is_accepted(): void
    {
        $contentType = 'application/json; q=0.9';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }

    #[Test]
    public function get_media_type_with_q_value_zero_is_rejected(): void
    {
        $contentType = 'application/json; q=0';

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function get_media_type_with_q_value_decimal_zero_is_rejected(): void
    {
        $contentType = 'application/json; q=0.0';

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function get_media_type_with_q_value_zero_after_charset_is_rejected(): void
    {
        $contentType = 'application/json; charset=utf-8; q=0';

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function get_media_type_with_q_value_zero_with_spaces_is_rejected(): void
    {
        $contentType = 'application/json; q = 0';

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function get_media_type_with_uppercase_q_parameter_is_rejected_when_zero(): void
    {
        $contentType = 'application/json; Q=0';

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->negotiator->getMediaType($contentType);
    }

    #[Test]
    public function get_media_type_with_q_value_zero_rejects_media_type_in_exception(): void
    {
        $contentType = 'application/json; q=0';

        try {
            $this->negotiator->getMediaType($contentType);
            $this->fail('Expected UnsupportedMediaTypeException was not thrown');
        } catch (UnsupportedMediaTypeException $exception) {
            $this->assertSame('application/json', $exception->mediaType);
        }
    }

    #[Test]
    public function get_media_type_with_low_q_value_still_accepted(): void
    {
        $contentType = 'application/json; q=0.001';
        $result = $this->negotiator->getMediaType($contentType);

        $this->assertSame('application/json', $result);
    }
}
