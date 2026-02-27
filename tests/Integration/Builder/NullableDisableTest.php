<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Duyler\OpenApi\Validator\Exception\ValidationException;

final class NullableDisableTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function disabled_nullable_rejects_null_values(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                    nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->disableNullableAsType()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => null,
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function enabled_nullable_allows_null_values(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                    nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => null,
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function disabled_nullable_array_items_rejects_null(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: array
                items:
                  type: string
                  nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->disableNullableAsType()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value1', null, 'value3',
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function enabled_nullable_array_items_accepts_null(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: array
                items:
                  type: string
                  nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value1', null, 'value3',
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function disabled_nullable_nested_object_rejects_null(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  user:
                    type: object
                    properties:
                      email:
                        type: string
                        nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->disableNullableAsType()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'user' => ['email' => null],
            ])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function enabled_nullable_nested_object_accepts_null(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  user:
                    type: object
                    properties:
                      email:
                        type: string
                        nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'user' => ['email' => null],
            ])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function disabled_nullable_anyof_rejects_null(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                anyOf:
                  - type: string
                  - type: integer
                  - type: string
                    nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->disableNullableAsType()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('null'));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function enabled_nullable_anyof_accepts_null(): void
    {
        $yaml = <<<YAML
openapi: 3.0.3
info:
  title: Test
  version: 1.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      operationId: testEndpoint
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                anyOf:
                  - type: string
                  - type: integer
                  - type: string
                    nullable: true
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/test');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('null'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }
}
