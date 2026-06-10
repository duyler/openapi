<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Nyholm\Psr7\Factory\Psr17Factory;

final class ValidatorReuseTest extends TestCase
{
    private const string SCHEMA_YAML = <<<YAML
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
components:
  schemas:
    User:
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
YAML;

    #[Test]
    public function request_validator_is_created_once_in_constructor(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $property = new ReflectionProperty(OpenApiValidator::class, 'requestValidator');

        $instance = $property->getValue($validator);

        $this->assertInstanceOf(RequestValidator::class, $instance);
    }

    #[Test]
    public function response_validator_is_created_once_in_constructor(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $property = new ReflectionProperty(OpenApiValidator::class, 'responseValidator');

        $instance = $property->getValue($validator);

        $this->assertInstanceOf(ResponseValidatorWithContext::class, $instance);
    }

    #[Test]
    public function ref_resolver_is_created_once_in_constructor(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $property = new ReflectionProperty(OpenApiValidator::class, 'refResolver');

        $instance = $property->getValue($validator);

        $this->assertInstanceOf(RefResolver::class, $instance);
    }

    #[Test]
    public function ref_resolver_reused_across_validate_schema_calls(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $property = new ReflectionProperty(OpenApiValidator::class, 'refResolver');

        $refResolverBefore = $property->getValue($validator);

        $data = ['name' => 'John', 'email' => 'john@example.com'];
        $validator->validateSchema($data, '#/components/schemas/User');
        $validator->validateSchema($data, '#/components/schemas/User');

        $refResolverAfter = $property->getValue($validator);

        $this->assertSame($refResolverBefore, $refResolverAfter);
    }

    #[Test]
    public function repeated_validate_schema_succeeds(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $validator->validateSchema($data, '#/components/schemas/User');
        $validator->validateSchema($data, '#/components/schemas/User');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function repeated_validate_request_succeeds(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $factory = new Psr17Factory();

        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"name":"John"}'));

        $operation1 = $validator->validateRequest($request);
        $operation2 = $validator->validateRequest($request);

        $this->assertSame($operation1->path, $operation2->path);
        $this->assertSame($operation1->method, $operation2->method);
    }
}
