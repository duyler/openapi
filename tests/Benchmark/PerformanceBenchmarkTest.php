<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Benchmark;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\StreamStubHelper;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use function sprintf;

final class PerformanceBenchmarkTest extends TestCase
{
    use StreamStubHelper;

    #[Test]
    public function object_allocations_per_request(): void
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

        gc_collect_cycles();
        $memoryBefore = memory_get_usage();

        for ($i = 0; $i < 50; $i++) {
            $request = $this->createPsr7Request(
                '/users',
                'POST',
                ['Content-Type' => 'application/json'],
                '{"name": "John", "email": "john@example.com"}',
            );
            $operation = $validator->validateRequest($request);

            $response = $this->createPsr7Response(201, '{"id": 1, "name": "John"}');
            $validator->validateResponse($response, $operation);
        }

        gc_collect_cycles();
        $memoryAfter = memory_get_usage();
        $memoryPerRequest = (int) (($memoryAfter - $memoryBefore) / 50);

        self::assertLessThan(50_000, $memoryPerRequest, 'Memory per request should be under 50KB');
    }

    #[Test]
    public function validation_time_simple_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /ping:
    get:
      responses:
        '200':
          description: Pong
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $iterations = 200;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $request = $this->createPsr7Request('/ping', 'GET');
            $validator->validateRequest($request);
        }

        $avgMs = ((microtime(true) - $start) * 1000.0) / (float) $iterations;

        self::assertLessThan(5.0, $avgMs, 'Simple schema avg validation should be under 5ms');
    }

    #[Test]
    public function validation_time_medium_schema(): void
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
                age:
                  type: integer
                  minimum: 0
                  maximum: 150
                role:
                  type: string
                  enum: [admin, user, guest]
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
                  email:
                    type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $iterations = 200;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $request = $this->createPsr7Request(
                '/users',
                'POST',
                ['Content-Type' => 'application/json'],
                '{"name": "John Doe", "email": "john@example.com", "age": 30, "role": "admin"}',
            );
            $validator->validateRequest($request);
        }

        $avgMs = ((microtime(true) - $start) * 1000.0) / (float) $iterations;

        self::assertLessThan(10.0, $avgMs, 'Medium schema avg validation should be under 10ms');
    }

    #[Test]
    public function validation_time_complex_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->build();

        $iterations = 200;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $request = $this->createPsr7Request('/pets', 'GET');
            $validator->validateRequest($request);
        }

        $avgMs = ((microtime(true) - $start) * 1000.0) / (float) $iterations;

        self::assertLessThan(10.0, $avgMs, 'Complex schema avg validation should be under 10ms');
    }

    #[Test]
    public function repeated_validation_caching_effect(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /items/{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: OK
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

        $request = $this->createPsr7Request('/items/42', 'GET');
        $validator->validateRequest($request);

        $iterations = 200;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $request = $this->createPsr7Request('/items/42', 'GET');
            $validator->validateRequest($request);
        }

        $avgMs = ((microtime(true) - $start) * 1000.0) / (float) $iterations;

        self::assertLessThan(5.0, $avgMs, 'Cached validation avg should be under 5ms');
    }

    #[Test]
    public function path_finder_no_exceptions_for_mismatches(): void
    {
        $yamlParts = ["openapi: 3.1.0\ninfo:\n  title: Test API\n  version: 1.0.0\npaths:"];

        for ($i = 0; $i < 100; $i++) {
            $yamlParts[] = sprintf(
                "  /resource%d/{id}:\n    get:\n      responses:\n        '200':\n          description: OK",
                $i,
            );
        }

        $yaml = implode("\n", $yamlParts);

        $document = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build()
            ->getDocument();

        $pathParser = new PathParser(new PathRegexCache());

        gc_collect_cycles();
        $memoryBefore = memory_get_usage();
        $start = microtime(true);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            foreach ($document->paths?->paths ?? [] as $pattern => $pathItem) {
                $pathParser->tryMatchPath('/resource99/123', $pattern);
            }
        }

        $duration = ((microtime(true) - $start) * 1000.0);
        gc_collect_cycles();
        $memoryAfter = memory_get_usage();
        $memoryGrowth = $memoryAfter - $memoryBefore;

        self::assertLessThan(100, $duration, '100-route tryMatchPath scan should be under 100ms');
        self::assertLessThan(1_000_000, $memoryGrowth, 'Memory growth for path scanning should be minimal');
    }

    private function createPsr7Request(
        string $uri,
        string $method,
        array $headers = [],
        string $body = '',
    ): ServerRequestInterface {
        /** @var ServerRequestInterface $request */
        $request = $this->createStub(ServerRequestInterface::class);

        $request
            ->method('getMethod')
            ->willReturn($method);

        $uriMock = $this->createStub(UriInterface::class);
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
            ->willReturnCallback(function (string $headerName): string {
                if ('Content-Type' === $headerName) {
                    return 'application/json';
                }

                if ('Cookie' === $headerName) {
                    return '';
                }

                return '';
            });

        $request
            ->method('getCookieParams')
            ->willReturn([]);

        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn($body);

        $this->configureReadableStream($stream, $body);

        $request
            ->method('getBody')
            ->willReturn($stream);

        return $request;
    }

    private function createPsr7Response(
        int $statusCode,
        string $body,
        string $contentType = 'application/json',
    ): ResponseInterface {
        /** @var ResponseInterface $response */
        $response = $this->createStub(ResponseInterface::class);

        $response
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $response
            ->method('getHeaders')
            ->willReturn(['Content-Type' => [$contentType]]);

        $response
            ->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use ($contentType): string {
                if ('Content-Type' === $name) {
                    return $contentType;
                }

                return '';
            });

        $stream = $this->createStub(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn($body);

        $this->configureReadableStream($stream, $body);

        $response
            ->method('getBody')
            ->willReturn($stream);

        return $response;
    }
}
