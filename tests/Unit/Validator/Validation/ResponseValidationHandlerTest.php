<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Validation;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

#[CoversClass(ResponseValidationHandler::class)]
final class ResponseValidationHandlerTest extends TestCase
{
    private const string SIMPLE_YAML = <<<YAML
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: A list of users
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  properties:
                    id:
                      type: integer
                    name:
                      type: string
  /users/{id}:
    get:
      summary: Get user
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: User found
          content:
            application/json:
              schema:
                type: object
                required:
                  - id
                  - name
                properties:
                  id:
                    type: integer
                  name:
                    type: string
        '404':
          description: User not found
YAML;

    private OpenApiValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->build();
    }

    #[Test]
    public function validate_passes_for_valid_json_response(): void
    {
        $response = $this->createMockResponse(200, '{"id":1,"name":"John"}', 'application/json');
        $operation = new Operation('/users/{id}', 'GET');

        $this->validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_passes_for_array_response(): void
    {
        $response = $this->createMockResponse(
            200,
            '[{"id":1,"name":"John"},{"id":2,"name":"Jane"}]',
            'application/json',
        );
        $operation = new Operation('/users', 'GET');

        $this->validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_passes_for_response_without_body(): void
    {
        $response = $this->createMockResponse(404, '', '');
        $operation = new Operation('/users/{id}', 'GET');

        $this->validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_throws_for_invalid_response_body(): void
    {
        $response = $this->createMockResponse(200, '{"name":"John"}', 'application/json');
        $operation = new Operation('/users/{id}', 'GET');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Required');

        $this->validator->validateResponse($response, $operation);
    }

    #[Test]
    public function validate_throws_for_wrong_type_in_response(): void
    {
        $response = $this->createMockResponse(200, '{"id":"not-an-int","name":"John"}', 'application/json');
        $operation = new Operation('/users/{id}', 'GET');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Expected type');

        $this->validator->validateResponse($response, $operation);
    }

    #[Test]
    public function validate_full_request_response_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->enableCoercion()
            ->build();

        $request = $this->createMockServerRequest('GET', '/users/42');
        $operation = $validator->validateRequest($request);

        $response = $this->createMockResponse(200, '{"id":42,"name":"John"}', 'application/json');

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    private function createMockResponse(
        int $statusCode,
        string $body,
        string $contentType,
    ): ResponseInterface {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        if ('' !== $contentType) {
            $response->method('getHeaderLine')->willReturnMap([
                ['Content-Type', $contentType],
            ]);
            $response->method('getHeaders')->willReturn(['Content-Type' => [$contentType]]);
        } else {
            $response->method('getHeaderLine')->willReturn('');
            $response->method('getHeaders')->willReturn([]);
        }

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $response->method('getBody')->willReturn($stream);

        return $response;
    }

    private function createMockServerRequest(
        string $method,
        string $uri,
    ): ServerRequestInterface {
        $request = $this->createStub(ServerRequestInterface::class);

        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('getPath')->willReturn(parse_url($uri, PHP_URL_PATH) ?? $uri);
        $uriStub->method('getQuery')->willReturn(parse_url($uri, PHP_URL_QUERY) ?? '');

        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getHeaders')->willReturn([]);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getBody')->willReturn($this->createStub(StreamInterface::class));

        return $request;
    }
}
