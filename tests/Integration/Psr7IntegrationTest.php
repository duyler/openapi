<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Psr7IntegrationTest extends TestCase
{
    #[Test]
    public function validate_request_with_psr7_request(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
              required:
                - name
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->createPsr7Request(
            '/users',
            'POST',
            ['Content-Type' => 'application/json'],
            '{"name": "John Doe"}',
        );

        $validator->validateRequest($request, '/users', 'POST');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_response_with_psr7_response(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
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
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $response = $this->createPsr7Response(
            200,
            ['Content-Type' => 'application/json'],
            '[{"id": 1, "name": "John"}]',
        );

        $validator->validateResponse($response, '/users', 'GET');

        $this->expectNotToPerformAssertions();
    }

    private function createPsr7Request(
        string $uri,
        string $method,
        array $headers = [],
        string $body = '',
    ): object {
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);

        $request
            ->method('getMethod')
            ->willReturn($method);

        $uriMock = $this->createMock(\Psr\Http\Message\UriInterface::class);
        $uriMock
            ->method('getPath')
            ->willReturn($uri);
        $uriMock
            ->method('getQuery')
            ->willReturn('');

        $request
            ->method('getUri')
            ->willReturn($uriMock);

        $request
            ->method('getHeaders')
            ->willReturn($headers);

        $request
            ->method('getHeaderLine')
            ->willReturnCallback(function ($headerName) use ($headers) {
                if ('Content-Type' === $headerName) {
                    return 'application/json';
                }

                if ('Cookie' === $headerName) {
                    return '';
                }

                return '';
            });

        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn($body);

        $request
            ->method('getBody')
            ->willReturn($stream);

        return $request;
    }

    private function createPsr7Response(
        int $statusCode,
        array $headers = [],
        string $body = '',
    ): object {
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        $response
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $response
            ->method('getHeaders')
            ->willReturn($headers);

        $response
            ->method('getHeaderLine')
            ->willReturn('application/json');

        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn($body);

        $response
            ->method('getBody')
            ->willReturn($stream);

        return $response;
    }
}
