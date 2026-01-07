<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
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
}
