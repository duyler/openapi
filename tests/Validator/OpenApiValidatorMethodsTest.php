<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;

final class OpenApiValidatorMethodsTest extends TestCase
{
    private const string ALL_METHODS_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '201':
          description: Created
    put:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: Updated
    patch:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: Patched
    delete:
      responses:
        '204':
          description: Deleted
    options:
      responses:
        '200':
          description: Options
    head:
      responses:
        '200':
          description: Head
    trace:
      responses:
        '200':
          description: Trace
YAML;

    #[Test]
    public function validateRequest_with_post_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('POST', '/test')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream('{"data":"test"}'));

        $operation = $validator->validateRequest($request);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validateRequest_with_put_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('PUT', '/test')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream('{"data":"test"}'));

        $operation = $validator->validateRequest($request);
        $this->assertSame('PUT', $operation->method);
    }

    #[Test]
    public function validateRequest_with_patch_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('PATCH', '/test')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream('{"data":"test"}'));

        $operation = $validator->validateRequest($request);
        $this->assertSame('PATCH', $operation->method);
    }

    #[Test]
    public function validateRequest_with_delete_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('DELETE', '/test');

        $operation = $validator->validateRequest($request);
        $this->assertSame('DELETE', $operation->method);
    }

    #[Test]
    public function validateRequest_with_options_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('OPTIONS', '/test');

        $operation = $validator->validateRequest($request);
        $this->assertSame('OPTIONS', $operation->method);
    }

    #[Test]
    public function validateRequest_with_head_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('HEAD', '/test');

        $operation = $validator->validateRequest($request);
        $this->assertSame('HEAD', $operation->method);
    }

    #[Test]
    public function validateRequest_with_trace_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $request = new Psr17Factory()
            ->createServerRequest('TRACE', '/test');

        $operation = $validator->validateRequest($request);
        $this->assertSame('TRACE', $operation->method);
    }

    #[Test]
    public function validateResponse_with_post_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'POST');
        $response = new Psr17Factory()
            ->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream('{"success":true}'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateResponse_with_put_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'PUT');
        $response = new Psr17Factory()
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream('{"success":true}'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateResponse_with_patch_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'PATCH');
        $response = new Psr17Factory()
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream('{"success":true}'));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateResponse_with_delete_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'DELETE');
        $response = new Psr17Factory()
            ->createResponse(204);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateResponse_with_options_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'OPTIONS');
        $response = new Psr17Factory()
            ->createResponse(200);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateResponse_with_head_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'HEAD');
        $response = new Psr17Factory()
            ->createResponse(200);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateResponse_with_trace_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ALL_METHODS_YAML)
            ->build();

        $operation = new Operation('/test', 'TRACE');
        $response = new Psr17Factory()
            ->createResponse(200);

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }
}
