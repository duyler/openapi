<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
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
}
