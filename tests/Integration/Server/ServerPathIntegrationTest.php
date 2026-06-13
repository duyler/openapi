<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Server;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerPathIntegrationTest extends TestCase
{
    private const string MULTI_SERVER_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Multi-Server API
  version: 1.0.0
servers:
  - url: https://api.example.com/v1
    description: Production
paths:
  /users:
    get:
      operationId: listUsers
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
      responses:
        '200':
          description: Users list
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  properties:
                    id:
                      type: string
                      format: uuid
                    name:
                      type: string
  /users/{id}:
    get:
      operationId: getUser
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: User
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: string
                    format: uuid
                  name:
                    type: string
  /products:
    post:
      operationId: createProduct
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
      responses:
        '201':
          description: Created
YAML;

    private const string WEBHOOK_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Webhook API
  version: 1.0.0
servers:
  - url: https://api.example.com/v1
    description: Production
webhooks:
  payment.completed:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - payment_id
                - status
              properties:
                payment_id:
                  type: string
                status:
                  type: string
                  enum: [completed, failed]
      responses:
        '200':
          description: OK
paths:
  /health:
    get:
      operationId: healthCheck
      responses:
        '200':
          description: Healthy
YAML;
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function full_request_response_cycle_with_base_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_SERVER_YAML)
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/v1/products')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream('{"name": "Test Product"}'));

        $operation = $validator->validateRequest($request);

        self::assertSame('/products', $operation->path);
        self::assertSame('POST', $operation->method);

        $response = $this->psrFactory->createResponse(201);

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function response_validation_with_base_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_SERVER_YAML)
            ->enableServerPathResolution()
            ->build();

        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $request = $this->psrFactory->createServerRequest('GET', '/v1/users/' . $uuid);

        $operation = $validator->validateRequest($request);

        self::assertSame('/users/{id}', $operation->path);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                '{"id": "' . $uuid . '", "name": "John"}',
            ));

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function coercion_works_with_base_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_SERVER_YAML)
            ->enableServerPathResolution()
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/v1/users')
            ->withQueryParams(['limit' => '10']);

        $operation = $validator->validateRequest($request);

        self::assertSame('/users', $operation->path);
        self::assertSame('GET', $operation->method);
    }

    #[Test]
    public function events_receive_original_path(): void
    {
        $capturedPath = null;

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function (ValidationStartedEvent $event) use (&$capturedPath): void {
                    $capturedPath = $event->path;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTI_SERVER_YAML)
            ->enableServerPathResolution()
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/v1/users');

        $validator->validateRequest($request);

        self::assertSame('/v1/users', $capturedPath);
    }

    #[Test]
    public function webhook_validation_unaffected_by_server_flag(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WEBHOOK_YAML)
            ->enableServerPathResolution()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                '{"payment_id": "abc123", "status": "completed"}',
            ));

        $operation = $validator->validateWebhook($request, 'payment.completed');

        self::assertSame('payment.completed', $operation->path);
        self::assertSame('POST', $operation->method);
    }
}
