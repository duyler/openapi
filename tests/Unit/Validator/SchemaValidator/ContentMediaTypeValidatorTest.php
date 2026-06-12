<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\InvalidContentMediaTypeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentMediaTypeValidator::class)]
class ContentMediaTypeValidatorTest extends TestCase
{
    private ContentMediaTypeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContentMediaTypeValidator();
    }

    #[Test]
    public function skip_when_content_media_type_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_data_is_not_string(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->validator->validate(123, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_valid_json_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->validator->validate('{"key": "value"}', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_json_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not json at all', $schema);
    }

    #[Test]
    public function throw_error_for_empty_json_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/json');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function validate_valid_xml_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $this->validator->validate('<root><item>test</item></root>', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_xml_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not xml', $schema);
    }

    #[Test]
    public function xxe_attack_is_blocked_in_xml_validation(): void
    {
        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<root>&xxe;</root>
XML;

        $schema = new Schema(type: 'string', contentMediaType: 'application/xml');

        $this->validator->validate($xxePayload, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_valid_text_plain(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/plain');

        $this->validator->validate('any text content', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_text_plain(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/plain');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function validate_valid_html(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/html');

        $this->validator->validate('<html><body>Hello</body></html>', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_html(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/html');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('no html tags here', $schema);
    }

    #[Test]
    public function validate_valid_pdf(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/pdf');

        $this->validator->validate('%PDF-1.4 binary content', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_pdf(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/pdf');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a pdf', $schema);
    }

    #[Test]
    public function validate_valid_octet_stream(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $this->validator->validate('binary data', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_octet_stream(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function validate_valid_png(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/png');

        $this->validator->validate("\x89PNG\r\n\x1a\nbinary data", $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_png(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/png');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a png', $schema);
    }

    #[Test]
    public function validate_valid_jpeg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/jpeg');

        $this->validator->validate("\xFF\xD8\xFF\xE0binary data", $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_jpeg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/jpeg');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a jpeg', $schema);
    }

    #[Test]
    public function validate_valid_gif87a(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/gif');

        $this->validator->validate('GIF87abinary data', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_valid_gif89a(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/gif');

        $this->validator->validate('GIF89abinary data', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_gif(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/gif');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not a gif', $schema);
    }

    #[Test]
    public function validate_valid_svg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/svg+xml');

        $this->validator->validate('<svg xmlns="http://www.w3.org/2000/svg"><circle r="10"/></svg>', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_svg(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'image/svg+xml');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not svg', $schema);
    }

    #[Test]
    public function validate_valid_multipart_form_data(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $this->validator->validate("--boundary\r\nContent-Disposition: form-data; name=\"field\"\r\n\r\nvalue\r\n--boundary--", $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_multipart_form_data(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'multipart/form-data');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('not multipart', $schema);
    }

    #[Test]
    public function validate_valid_url_encoded(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-www-form-urlencoded');

        $this->validator->validate('key=value&foo=bar', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_url_encoded(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-www-form-urlencoded');

        $this->validator->validate('', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_url_encoded(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/x-www-form-urlencoded');

        $this->expectException(InvalidContentMediaTypeException::class);

        $this->validator->validate('=value_only', $schema);
    }

    #[Test]
    public function validate_text_xml_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/xml');

        $this->validator->validate('<root>content</root>', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_for_unknown_supported_media_type(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $this->validator->validate('data', $schema);

        $this->expectNotToPerformAssertions();
    }
}
