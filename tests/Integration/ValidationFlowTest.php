<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Psr15\Operation;
use Duyler\OpenApi\Psr15\ValidationMiddlewareBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ValidationFlowTest extends TestCase
{
    private const SIMPLE_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  name:
                    type: string
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
                required:
                  - id
                  - name
YAML;

    #[Test]
    public function full_validation_flow_works(): void
    {
        $middleware = (new ValidationMiddlewareBuilder())
            ->fromYamlString(self::SIMPLE_YAML)
            ->buildMiddleware();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('GET', '/users/123');

        $processedOperation = null;
        $handler = $this->createMockHandler(function ($req) use (&$processedOperation, $factory) {
            $processedOperation = $req->getAttribute(Operation::class);
            return $factory->createResponse(200)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream(json_encode(['id' => '123', 'name' => 'John'])));
        });

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($processedOperation);
        $this->assertSame('/users/{id}', $processedOperation->path);
        $this->assertSame('GET', $processedOperation->method);
    }

    #[Test]
    public function request_validation_fails_on_invalid_data(): void
    {
        $middleware = (new ValidationMiddlewareBuilder())
            ->fromYamlString(self::SIMPLE_YAML)
            ->buildMiddleware();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(json_encode(['name' => 'John'])));

        $handler = $this->createMockHandler(function ($req) {
            return new Response();
        });

        $response = $middleware->process($request, $handler);

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertFalse($body['success']);
    }

    #[Test]
    public function operation_is_available_in_handler(): void
    {
        $middleware = (new ValidationMiddlewareBuilder())
            ->fromYamlString(self::SIMPLE_YAML)
            ->buildMiddleware();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(json_encode(['name' => 'John', 'email' => 'john@example.com'])));

        $operationPath = null;
        $operationMethod = null;
        $handler = $this->createMockHandler(function ($req) use (&$operationPath, &$operationMethod, $factory) {
            $operation = $req->getAttribute(Operation::class);
            $operationPath = $operation->path;
            $operationMethod = $operation->method;
            return $factory->createResponse(201)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream(json_encode(['id' => 1, 'name' => 'John'])));
        });

        $response = $middleware->process($request, $handler);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/users', $operationPath);
        $this->assertSame('POST', $operationMethod);
    }

    #[Test]
    public function custom_request_error_handler_is_called(): void
    {
        $errorCallbackInvoked = false;
        $factory = new Psr17Factory();
        $middleware = (new ValidationMiddlewareBuilder())
            ->fromYamlString(self::SIMPLE_YAML)
            ->onRequestError(function ($e, $req) use (&$errorCallbackInvoked, $factory) {
                $errorCallbackInvoked = true;
                return (new Response(400))
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($factory->createStream(json_encode(['custom' => true])));
            })
            ->buildMiddleware();

        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(json_encode(['name' => 'John'])));

        $handler = $this->createMockHandler(function ($req) {
            return new Response();
        });

        $response = $middleware->process($request, $handler);

        $this->assertTrue($errorCallbackInvoked);
        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['custom']);
    }

    #[Test]
    public function custom_response_error_handler_is_called(): void
    {
        $errorCallbackInvoked = false;
        $factory = new Psr17Factory();
        $middleware = (new ValidationMiddlewareBuilder())
            ->fromYamlString(self::SIMPLE_YAML)
            ->onResponseError(function ($e, $req, $resp) use (&$errorCallbackInvoked, $factory) {
                $errorCallbackInvoked = true;
                return (new Response(503))
                    ->withHeader('Content-Type', 'application/json')
                    ->withBody($factory->createStream(json_encode(['service_unavailable' => true])));
            })
            ->buildMiddleware();

        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream(json_encode(['name' => 'John', 'email' => 'john@example.com'])));

        $handler = $this->createMockHandler(function ($req) use ($factory) {
            return $factory->createResponse(201)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream(json_encode(['name' => 'John'])));
        });

        $response = $middleware->process($request, $handler);

        $this->assertTrue($errorCallbackInvoked);
        $this->assertSame(503, $response->getStatusCode());
        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['service_unavailable']);
    }

    private function createMockHandler(callable $callback): RequestHandlerInterface
    {
        return new class ($callback) implements RequestHandlerInterface {
            public function __construct(
                private readonly mixed $callback,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->callback)($request);
            }
        };
    }
}
