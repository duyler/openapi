<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationBenchTest extends TestCase
{
    #[Test]
    public function benchmark_simple_validation(): void
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
                  minLength: 1
                  maxLength: 100
                email:
                  type: string
                  format: email
              required:
                - name
                - email
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $iterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $request = $this->createPsr7Request(
                '/users',
                'POST',
                ['Content-Type' => 'application/json'],
                '{"name": "John Doe", "email": "john@example.com"}',
            );

            $validator->validateRequest($request, '/users', 'POST');
        }

        $duration = (microtime(true) - $start) * 1000;
        $avgDuration = $duration / $iterations;

        self::assertLessThan(10, $avgDuration, 'Average validation should be under 10ms');
    }

    #[Test]
    public function benchmark_schema_validation(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
        email:
          type: string
          format: email
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $iterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $validator->validateSchema(
                ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
                '#/components/schemas/User',
            );
        }

        $duration = (microtime(true) - $start) * 1000;
        $avgDuration = $duration / $iterations;

        self::assertLessThan(5, $avgDuration, 'Average schema validation should be under 5ms');
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
            ->willReturnCallback(function ($headerName) {
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
}
