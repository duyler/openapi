<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Request;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\HeadersValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\QueryParametersValidator;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\QueryStringValidator;
use Duyler\OpenApi\Validator\Request\RequestBodyValidator;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/** @internal */
final class RequestValidatorIntegrationTest extends TestCase
{
    private RequestValidator $validator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $pathParser = new PathParser();
        $pathParamsValidator = new PathParametersValidator($schemaValidator, $deserializer, $coercer);
        $queryParser = new QueryParser();
        $queryParamsValidator = new QueryParametersValidator($schemaValidator, $deserializer, $coercer);
        $headersValidator = new HeadersValidator($schemaValidator, $deserializer, $coercer);
        $cookieValidator = new CookieValidator($schemaValidator, $deserializer, $coercer);
        $negotiator = new ContentTypeNegotiator();
        $jsonParser = new JsonBodyParser();
        $formParser = new FormBodyParser();
        $multipartParser = new MultipartBodyParser();
        $textParser = new TextBodyParser();
        $xmlParser = new XmlBodyParser();
        $bodyValidator = new RequestBodyValidator(
            $schemaValidator,
            $negotiator,
            $jsonParser,
            $formParser,
            $multipartParser,
            $textParser,
            $xmlParser,
        );

        $queryStringValidator = new QueryStringValidator($queryParser, $schemaValidator);

        $this->validator = new RequestValidator(
            $pathParser,
            $pathParamsValidator,
            $queryParser,
            $queryParamsValidator,
            $queryStringValidator,
            $headersValidator,
            $cookieValidator,
            $bodyValidator,
        );
    }

    #[Test]
    public function validate_complete_request(): void
    {
        $request = $this->createMockServerRequest(
            uri: '/users/123?active=true',
            queryParams: ['active' => 'true'],
            headers: ['X-Custom-Header' => 'value'],
            cookies: ['session' => 'abc123'],
            body: '{"name":"John"}',
            contentType: 'application/json',
        );

        $operation = new Operation(
            parameters: new Parameters([
                new Parameter(
                    name: 'id',
                    in: 'path',
                    required: true,
                    schema: new Schema(type: 'string'),
                ),
                new Parameter(
                    name: 'active',
                    in: 'query',
                    schema: new Schema(type: 'string'),  // Query params are strings
                ),
                new Parameter(
                    name: 'X-Custom-Header',
                    in: 'header',
                    schema: new Schema(type: 'string'),
                ),
                new Parameter(
                    name: 'session',
                    in: 'cookie',
                    schema: new Schema(type: 'string'),
                ),
            ]),
            requestBody: new RequestBody(
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            properties: [
                                'name' => new Schema(type: 'string'),
                            ],
                        ),
                    ),
                ]),
            ),
        );

        $this->validator->validate($request, $operation, '/users/{id}');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_real_api_request(): void
    {
        $request = $this->createMockServerRequest(
            uri: '/posts/456/comments/789',
            queryParams: ['limit' => '10'],
            headers: ['Authorization' => 'Bearer token123'],
            cookies: [],
            body: '',
            contentType: '',
        );

        $operation = new Operation(
            parameters: new Parameters([
                new Parameter(
                    name: 'postId',
                    in: 'path',
                    required: true,
                    schema: new Schema(type: 'string'),
                ),
                new Parameter(
                    name: 'commentId',
                    in: 'path',
                    required: true,
                    schema: new Schema(type: 'string'),
                ),
                new Parameter(
                    name: 'limit',
                    in: 'query',
                    schema: new Schema(type: 'string'),  // Query params are strings
                ),
            ]),
        );

        $this->validator->validate($request, $operation, '/posts/{postId}/comments/{commentId}');

        $this->expectNotToPerformAssertions();
    }

    private function createMockServerRequest(
        string $uri,
        array $queryParams,
        array $headers,
        array $cookies,
        string $body,
        string $contentType,
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);

        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn($uri);
        $uriMock->method('getQuery')->willReturn(http_build_query($queryParams));

        $request->method('getUri')->willReturn($uriMock);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getHeaders')->willReturn($headers);
        $request->method('getHeaderLine')->willReturnMap([
            ['Cookie', http_build_query($cookies, '', '; ')],
            ['Content-Type', $contentType],
        ]);
        $request->method('getCookieParams')->willReturn($cookies);

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($body);
        $request->method('getBody')->willReturn($bodyMock);

        return $request;
    }
}
