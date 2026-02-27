<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\OpenApiValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

final class OpenApiValidatorTest extends TestCase
{
    private const string SIMPLE_YAML = <<<YAML
openapi: 3.0.3
info:
  title: Sample API
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
    public function create_validator_from_yaml(): void
    {
        $this->assertSame('Sample API', $this->validator->document->info->title);
        $this->assertSame('1.0.0', $this->validator->document->info->version);
    }

    #[Test]
    public function throw_error_for_unknown_path(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Operation not found: GET /unknown');

        $this->validator->validateRequest(
            $this->createMockServerRequest('GET', '/unknown'),
        );
    }

    #[Test]
    public function throw_error_for_unknown_method(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Operation not found: DELETE /users');

        $this->validator->validateRequest(
            $this->createMockServerRequest('DELETE', '/users'),
        );
    }

    #[Test]
    public function format_errors(): void
    {
        $request = $this->createMockServerRequest('GET', '/users?limit=invalid');

        try {
            $this->validator->validateRequest($request);
            $this->fail('Expected exception to be thrown');
        } catch (Throwable $e) {
            $this->assertStringContainsString('Expected type', $e->getMessage());
        }
    }

    #[Test]
    public function find_operation_successfully(): void
    {
        $request = $this->createMockServerRequest('GET', '/users');

        $operation = $this->validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function validate_request_auto_finds_operation(): void
    {
        $request = $this->createMockServerRequest('GET', '/users');
        $validator = $this->createValidator();

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function validate_request_auto_throws_exception_for_unknown_path(): void
    {
        $request = $this->createMockServerRequest('GET', '/unknown/path');
        $validator = $this->createValidator();

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Operation not found: GET /unknown/path');

        $validator->validateRequest($request);
    }

    #[Test]
    public function validate_response_with_operation(): void
    {
        $response = $this->createMockResponse();
        $validator = $this->createValidator();
        $operation = new Operation('/users/{id}', 'GET');

        $this->expectNotToPerformAssertions();

        $validator->validateResponse($response, $operation);
    }

    /**
     * Create a mock PSR-7 server request
     */
    private function createMockServerRequest(string $method, string $uri)
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($this->createMockUri($uri));
        $request->method('getHeaders')->willReturn([]);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getBody')->willReturn($this->createMockStream(''));

        return $request;
    }

    private function createMockUri(string $uri)
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn(parse_url($uri, PHP_URL_PATH) ?? $uri);
        $uriMock->method('getQuery')->willReturn(parse_url($uri, PHP_URL_QUERY) ?? '');

        return $uriMock;
    }

    private function createMockStream(string $content)
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);

        return $stream;
    }

    private function createValidator(): OpenApiValidator
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->build();
    }

    private function createMockResponse()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getHeaderLine')->willReturn('application/json');
        $response->method('getBody')->willReturn($this->createMockStream('{"id": 1, "name": "John"}'));

        return $response;
    }
}
