<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Response\ResponseBodyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class ResponseBodyValidatorTest extends TestCase
{
    private ResponseBodyValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $negotiator = new ContentTypeNegotiator();
        $jsonParser = new JsonBodyParser();
        $formParser = new FormBodyParser();
        $multipartParser = new MultipartBodyParser();
        $textParser = new TextBodyParser();
        $xmlParser = new XmlBodyParser();

        $this->validator = new ResponseBodyValidator(
            $schemaValidator,
            $negotiator,
            $jsonParser,
            $formParser,
            $multipartParser,
            $textParser,
            $xmlParser,
        );
    }

    #[Test]
    public function validate_json_response(): void
    {
        $body = '{"id":123,"name":"John"}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                        'name' => new Schema(type: 'string'),
                    ],
                    required: ['id', 'name'],
                ),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_text_response(): void
    {
        $body = 'Plain text response';
        $contentType = 'text/plain';
        $content = new Content([
            'text/plain' => new MediaType(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_when_content_is_null(): void
    {
        $body = '{"id":123}';
        $contentType = 'application/json';

        $this->validator->validate($body, $contentType, null);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_when_media_type_not_found(): void
    {
        $body = '{"id":123}';
        $contentType = 'application/xml';
        $content = new Content([
            'application/json' => new MediaType(),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_body_with_json_content(): void
    {
        $body = '{"id":1,"name":"Test"}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                        'name' => new Schema(type: 'string'),
                    ],
                ),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_body_with_text_content(): void
    {
        $body = 'Hello World';
        $contentType = 'text/html';
        $content = new Content([
            'text/html' => new MediaType(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_response_body(): void
    {
        $body = '';
        $contentType = 'text/plain';
        $content = new Content([
            'text/plain' => new MediaType(),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_body_with_schema_validation_errors(): void
    {
        $body = '{"id":"not_a_number"}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($body, $contentType, $content);
    }

    #[Test]
    public function validate_response_body_with_required_fields_missing(): void
    {
        $body = '{"id":1}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                        'name' => new Schema(type: 'string'),
                    ],
                    required: ['id', 'name'],
                ),
            ),
        ]);

        $this->expectException(ValidationException::class);

        $this->validator->validate($body, $contentType, $content);
    }

    #[Test]
    public function validate_response_body_with_type_mismatch(): void
    {
        $body = '{"id":"string_value"}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($body, $contentType, $content);
    }

    #[Test]
    public function supports_multiple_response_content_types(): void
    {
        $jsonBody = '{"type":"json"}';
        $textBody = 'text response';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'type' => new Schema(type: 'string'),
                    ],
                ),
            ),
            'text/plain' => new MediaType(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($jsonBody, 'application/json', $content);
        $this->validator->validate($textBody, 'text/plain', $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_body_with_array_schema(): void
    {
        $body = '[1,2,3]';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'array',
                    items: new Schema(type: 'integer'),
                ),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_body_with_object_schema(): void
    {
        $body = '{"name":"Test","age":25}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: 'string'),
                        'age' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_body_with_nested_schema(): void
    {
        $body = '{"user":{"name":"John","age":30}}';
        $contentType = 'application/json';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'user' => new Schema(
                            type: 'object',
                            properties: [
                                'name' => new Schema(type: 'string'),
                                'age' => new Schema(type: 'integer'),
                            ],
                        ),
                    ],
                ),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_form_urlencoded_response(): void
    {
        $body = 'name=John&age=30';
        $contentType = 'application/x-www-form-urlencoded';
        $content = new Content([
            'application/x-www-form-urlencoded' => new MediaType(),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_xml_response(): void
    {
        $body = '<root><name>Test</name></root>';
        $contentType = 'application/xml';
        $content = new Content([
            'application/xml' => new MediaType(),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_text_csv_response(): void
    {
        $body = 'name,age\nJohn,30';
        $contentType = 'text/csv';
        $content = new Content([
            'text/csv' => new MediaType(
                schema: new Schema(type: 'string'),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_unknown_media_type(): void
    {
        $body = 'some content';
        $contentType = 'application/octet-stream';
        $content = new Content([
            'application/octet-stream' => new MediaType(),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_multipart_response(): void
    {
        $body = 'multipart-body-data';
        $contentType = 'multipart/form-data';
        $content = new Content([
            'multipart/form-data' => new MediaType(),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_charset_in_content_type(): void
    {
        $body = '{"id":1}';
        $contentType = 'application/json; charset=utf-8';
        $content = new Content([
            'application/json' => new MediaType(
                schema: new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                ),
            ),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_when_media_type_schema_is_null(): void
    {
        $body = 'raw body content';
        $contentType = 'application/octet-stream';
        $content = new Content([
            'application/octet-stream' => new MediaType(schema: null),
        ]);

        $this->validator->validate($body, $contentType, $content);

        $this->expectNotToPerformAssertions();
    }
}
