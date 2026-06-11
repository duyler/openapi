<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\InvalidContentMediaTypeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function validate_text_plain_always_passes(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/plain');

        $this->validator->validate('any text content', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_text_html_always_passes(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'text/html');

        $this->validator->validate('<html><body>Hello</body></html>', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pass_for_known_media_type_without_specific_validation(): void
    {
        $schema = new Schema(type: 'string', contentMediaType: 'application/octet-stream');

        $this->validator->validate('binary data', $schema);

        $this->expectNotToPerformAssertions();
    }
}
