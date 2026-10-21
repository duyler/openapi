<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\Psr7RequestStubFactory;
use Duyler\OpenApi\Validator\Operation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Psr7IntegrationTest extends TestCase
{
    use Psr7RequestStubFactory;

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

        $request = $this->createStubRequest(
            'POST',
            '/users',
            ['Content-Type' => 'application/json'],
            '{"name": "John Doe"}',
        );

        $validator->validateRequest($request);

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

        $operation = new Operation('/users', 'GET');

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    private function createPsr7Response(
        int $statusCode,
        array $headers = [],
        string $body = '',
    ): object {
        $response = $this->createStub(ResponseInterface::class);

        $response
            ->method('getStatusCode')
            ->willReturn($statusCode);

        $response
            ->method('getHeaders')
            ->willReturn($headers);

        $response
            ->method('getHeaderLine')
            ->willReturn('application/json');

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
