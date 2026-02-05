<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Response\ResponseBodyValidator;
use Duyler\OpenApi\Validator\Response\ResponseTypeCoercer;
use Duyler\OpenApi\Validator\Response\ResponseHeadersValidator;
use Duyler\OpenApi\Validator\Response\ResponseValidator;
use Duyler\OpenApi\Validator\Response\StatusCodeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** @internal */
final class ResponseValidatorIntegrationTest extends TestCase
{
    private ResponseValidator $validator;

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
        $typeCoercer = new ResponseTypeCoercer();
        $bodyParser = new BodyParser($jsonParser, $formParser, $multipartParser, $textParser, $xmlParser);

        $statusCodeValidator = new StatusCodeValidator();
        $headersValidator = new ResponseHeadersValidator($schemaValidator);
        $bodyValidator = new ResponseBodyValidator(
            $schemaValidator,
            $bodyParser,
            $negotiator,
            $typeCoercer,
        );

        $this->validator = new ResponseValidator(
            $statusCodeValidator,
            $headersValidator,
            $bodyValidator,
        );
    }

    #[Test]
    public function validate_successful_response(): void
    {
        $response = $this->createMockResponse(
            statusCode: 200,
            headers: ['X-Custom-Header' => 'value'],
            body: '{"id":123,"name":"John"}',
            contentType: 'application/json',
        );

        $operation = new Operation(
            responses: new Responses([
                '200' => new Response(
                    description: 'Success',
                    headers: new Headers([
                        'X-Custom-Header' => new Header(
                            schema: new Schema(type: 'string'),
                        ),
                    ]),
                    content: new Content([
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
                    ]),
                ),
            ]),
        );

        $this->validator->validate($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_error_response(): void
    {
        $response = $this->createMockResponse(
            statusCode: 404,
            headers: [],
            body: '{"error":"Not found"}',
            contentType: 'application/json',
        );

        $operation = new Operation(
            responses: new Responses([
                '404' => new Response(
                    description: 'Not found',
                    content: new Content([
                        'application/json' => new MediaType(
                            schema: new Schema(
                                type: 'object',
                                properties: [
                                    'error' => new Schema(type: 'string'),
                                ],
                            ),
                        ),
                    ]),
                ),
            ]),
        );

        $this->validator->validate($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function use_default_for_unknown_code(): void
    {
        $response = $this->createMockResponse(
            statusCode: 500,
            headers: [],
            body: '{"error":"Server error"}',
            contentType: 'application/json',
        );

        $operation = new Operation(
            responses: new Responses([
                'default' => new Response(
                    description: 'Default error',
                    content: new Content([
                        'application/json' => new MediaType(
                            schema: new Schema(
                                type: 'object',
                                properties: [
                                    'error' => new Schema(type: 'string'),
                                ],
                            ),
                        ),
                    ]),
                ),
            ]),
        );

        $this->validator->validate($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    private function createMockResponse(
        int $statusCode,
        array $headers,
        string $body,
        string $contentType,
    ): ResponseInterface {
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getHeaderLine')->willReturnMap([
            ['Content-Type', $contentType],
        ]);

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($body);
        $response->method('getBody')->willReturn($bodyMock);

        return $response;
    }
}
