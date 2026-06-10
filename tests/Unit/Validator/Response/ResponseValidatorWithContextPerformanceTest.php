<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(ResponseValidatorWithContext::class)]
final class ResponseValidatorWithContextPerformanceTest extends TestCase
{
    #[Test]
    public function repeated_validate_does_not_create_redundant_objects(): void
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
                email:
                  type: string
                  format: email
              required:
                - name
                - email
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
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

        $operation = $validator->validateRequest(
            $this->createPsr7Request(
                '/users',
                'POST',
                ['Content-Type' => 'application/json'],
                '{"name": "John", "email": "john@example.com"}',
            ),
        );

        $response = $this->createPsr7Response(201, '{"id": 1, "name": "John"}');

        gc_collect_cycles();
        $memoryBefore = memory_get_usage();

        for ($i = 0; $i < 50; $i++) {
            $validator->validateResponse($response, $operation);
        }

        gc_collect_cycles();
        $memoryAfter = memory_get_usage();
        $memoryPerCall = (int) (($memoryAfter - $memoryBefore) / 50);

        $this->assertLessThan(20_000, $memoryPerCall, 'Memory per repeated validate call should be minimal');
    }

    #[Test]
    public function body_parser_reused_across_validate_calls(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /items:
    get:
      responses:
        '200':
          description: OK
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

        $operation = $validator->validateRequest($this->createPsr7Request('/items', 'GET'));
        $response = $this->createPsr7Response(200, '[{"id": 1, "name": "Item"}]');

        $iterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $validator->validateResponse($response, $operation);
        }

        $avgMs = ((microtime(true) - $start) * 1000.0) / (float) $iterations;

        $this->assertLessThan(10.0, $avgMs, 'Repeated response validation avg should be under 10ms');
    }

    private function createPsr7Request(
        string $uri,
        string $method,
        array $headers = [],
        string $body = '',
    ): ServerRequestInterface {
        /** @var ServerRequestInterface $request */
        $request = $this->createStub(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);

        $uriMock = $this->createStub(UriInterface::class);
        $uriMock->method('getPath')->willReturn($uri);
        $uriMock->method('getQuery')->willReturn('');

        $request->method('getUri')->willReturn($uriMock);
        $request->method('getHeaders')->willReturn($headers);
        $request->method('getHeaderLine')->willReturnCallback(function (string $name): string {
            if ('Content-Type' === $name) {
                return 'application/json';
            }

            if ('Cookie' === $name) {
                return '';
            }

            return '';
        });
        $request->method('getCookieParams')->willReturn([]);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $request->method('getBody')->willReturn($stream);

        return $request;
    }

    private function createPsr7Response(
        int $statusCode,
        string $body,
        string $contentType = 'application/json',
    ): ResponseInterface {
        /** @var ResponseInterface $response */
        $response = $this->createStub(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getHeaders')->willReturn(['Content-Type' => [$contentType]]);
        $response->method('getHeaderLine')->willReturnCallback(function (string $name) use ($contentType): string {
            if ('Content-Type' === $name) {
                return $contentType;
            }

            return '';
        });

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $response->method('getBody')->willReturn($stream);

        return $response;
    }
}
