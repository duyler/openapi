<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Request\RequestBodyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class RequestBodyValidatorTest extends TestCase
{
    private RequestBodyValidator $validator;

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

        $this->validator = new RequestBodyValidator(
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
    public function validate_json_body(): void
    {
        $body = '{"name":"John","age":30}';
        $contentType = 'application/json';
        $requestBody = new RequestBody(
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'age' => new Schema(type: 'integer'),
                        ],
                        required: ['name', 'age'],
                    ),
                ),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_form_body(): void
    {
        $body = 'name=John&age=30';
        $contentType = 'application/x-www-form-urlencoded';
        $requestBody = new RequestBody(
            content: new Content([
                'application/x-www-form-urlencoded' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'age' => new Schema(type: 'string'),
                        ],
                    ),
                ),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_unsupported_media_type(): void
    {
        $body = '{"name":"John"}';
        $contentType = 'application/xml';
        $requestBody = new RequestBody(
            content: new Content([
                'application/json' => new MediaType(),
            ]),
        );

        $this->expectException(UnsupportedMediaTypeException::class);

        $this->validator->validate($body, $contentType, $requestBody);
    }

    #[Test]
    public function skip_validation_when_request_body_is_null(): void
    {
        $body = '{"name":"John"}';
        $contentType = 'application/json';

        $this->validator->validate($body, $contentType, null);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_when_content_is_null(): void
    {
        $body = '{"name":"John"}';
        $contentType = 'application/json';
        $requestBody = new RequestBody();

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_multipart(): void
    {
        $body = 'field1=value1&field2=value2';
        $contentType = 'multipart/form-data';
        $requestBody = new RequestBody(
            content: new Content([
                'multipart/form-data' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_text_content(): void
    {
        $body = 'plain text content';
        $contentType = 'text/plain';
        $requestBody = new RequestBody(
            content: new Content([
                'text/plain' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_html_content(): void
    {
        $body = '<html><body>content</body></html>';
        $contentType = 'text/html';
        $requestBody = new RequestBody(
            content: new Content([
                'text/html' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_csv_content(): void
    {
        $body = 'header1,header2';
        $contentType = 'text/csv';
        $requestBody = new RequestBody(
            content: new Content([
                'text/csv' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_xml_content(): void
    {
        $body = '<root><item>value</item></root>';
        $contentType = 'application/xml';
        $requestBody = new RequestBody(
            content: new Content([
                'application/xml' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_text_xml_content(): void
    {
        $body = '<root><item>value</item></root>';
        $contentType = 'text/xml';
        $requestBody = new RequestBody(
            content: new Content([
                'text/xml' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_request_body(): void
    {
        $body = '';
        $contentType = 'text/plain';
        $requestBody = new RequestBody(
            content: new Content([
                'text/plain' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_without_schema(): void
    {
        $body = '{"name":"John","age":30}';
        $contentType = 'application/json';
        $requestBody = new RequestBody(
            content: new Content([
                'application/json' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_schema_validation_errors(): void
    {
        $body = '{"name":"John","age":"invalid"}';
        $contentType = 'application/json';
        $requestBody = new RequestBody(
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'age' => new Schema(type: 'integer'),
                        ],
                        required: ['name', 'age'],
                    ),
                ),
            ]),
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate($body, $contentType, $requestBody);
    }

    #[Test]
    public function supports_multiple_media_types(): void
    {
        $body = 'name=John&age=30';
        $contentType = 'application/x-www-form-urlencoded';
        $requestBody = new RequestBody(
            content: new Content([
                'application/json' => new MediaType(),
                'application/x-www-form-urlencoded' => new MediaType(),
                'text/plain' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_body_with_required_fields(): void
    {
        $body = '{"name":"John","age":30}';
        $contentType = 'application/json';
        $requestBody = new RequestBody(
            required: true,
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'age' => new Schema(type: 'integer'),
                        ],
                        required: ['name', 'age'],
                    ),
                ),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_missing_required_field_in_schema(): void
    {
        $body = '{"name":"John"}';
        $contentType = 'application/json';
        $requestBody = new RequestBody(
            content: new Content([
                'application/json' => new MediaType(
                    schema: new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'age' => new Schema(type: 'integer'),
                        ],
                        required: ['name', 'age'],
                    ),
                ),
            ]),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate($body, $contentType, $requestBody);
    }

    #[Test]
    public function handle_unknown_media_type(): void
    {
        $body = 'custom data';
        $contentType = 'application/custom-type';
        $requestBody = new RequestBody(
            content: new Content([
                'application/custom-type' => new MediaType(),
            ]),
        );

        $this->validator->validate($body, $contentType, $requestBody);

        $this->expectNotToPerformAssertions();
    }
}
