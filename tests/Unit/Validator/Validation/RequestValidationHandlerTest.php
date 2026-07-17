<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Validation;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\StreamStubHelper;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

#[CoversClass(RequestValidationHandler::class)]
final class RequestValidationHandlerTest extends TestCase
{
    use StreamStubHelper;

    private const string SIMPLE_YAML = <<<YAML
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 100
      responses:
        '200':
          description: A list of users
    post:
      summary: Create user
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
                - email
              properties:
                name:
                  type: string
                email:
                  type: string
                  format: email
      responses:
        '201':
          description: User created
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
    public function validate_returns_operation_for_valid_get_request(): void
    {
        $request = $this->createMockServerRequest('GET', '/users');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function validate_returns_operation_for_valid_post_request(): void
    {
        $request = $this->createMockServerRequest(
            'POST',
            '/users',
            '{"name":"John","email":"john@example.com"}',
            'application/json',
        );

        $operation = $this->validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_returns_operation_for_path_with_parameters(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->enableCoercion()
            ->build();

        $request = $this->createMockServerRequest('GET', '/users/123');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users/{id}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function validate_throws_for_invalid_query_parameter(): void
    {
        $request = $this->createMockServerRequest('GET', '/users?limit=invalid');

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Expected type');

        $this->validator->validateRequest($request);
    }

    #[Test]
    public function validate_throws_for_invalid_request_body(): void
    {
        $request = $this->createMockServerRequest(
            'POST',
            '/users',
            '{"name":"John"}',
            'application/json',
        );

        $this->expectException(Throwable::class);
        $this->expectExceptionMessage('Required');

        $this->validator->validateRequest($request);
    }

    private function createMockServerRequest(
        string $method,
        string $uri,
        string $body = '',
        string $contentType = '',
    ): ServerRequestInterface {
        $request = $this->createStub(ServerRequestInterface::class);

        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('getPath')->willReturn(parse_url($uri, PHP_URL_PATH) ?? $uri);
        $uriStub->method('getQuery')->willReturn(parse_url($uri, PHP_URL_QUERY) ?? '');

        $streamStub = $this->createStub(StreamInterface::class);
        $streamStub->method('__toString')->willReturn($body);
        $this->configureReadableStream($streamStub, $body);

        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uriStub);
        $request->method('getHeaders')->willReturn([]);
        $request->method('getHeaderLine')->willReturn($contentType);
        $request->method('getBody')->willReturn($streamStub);

        return $request;
    }
}
