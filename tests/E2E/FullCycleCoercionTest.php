<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FullCycleCoercionTest extends TestCase
{
    private const string COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Coercion Cycle API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: listUsersByAge
      parameters:
        - name: age
          in: query
          required: true
          schema:
            type: integer
            minimum: 18
            maximum: 120
      responses:
        '200':
          description: Filtered user payload echoing coerced age
          content:
            application/json:
              schema:
                type: object
                required:
                  - age
                properties:
                  age:
                    type: integer
                    minimum: 18
                    maximum: 120
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function full_cycle_with_coercion_validates_request_and_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->factory->createServerRequest('GET', '/users?age=25');

        $operation = $validator->validateRequest($request);

        self::assertSame('GET', $operation->method);
        self::assertSame('/users', $operation->path);

        $response = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"age":25}'));

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function coercion_proven_by_minimum_constraint_evaluated_on_coerced_int(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->factory->createServerRequest('GET', '/users?age=17');

        $caught = null;
        try {
            $validator->validateRequest($request);
            self::fail('Expected MinimumError when coerced int 17 violates minimum: 18');
        } catch (MinimumError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('minimum', $caught->keyword());
        self::assertSame(17.0, $caught->params()['actual']);
        self::assertSame(18.0, $caught->params()['minimum']);
    }

    #[Test]
    public function without_coercion_string_query_param_rejected_before_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COERCION_SPEC)
            ->build();

        $request = $this->factory->createServerRequest('GET', '/users?age=25');

        $this->expectException(TypeMismatchError::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function with_coercion_non_numeric_string_rejected_before_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->factory->createServerRequest('GET', '/users?age=abc');

        $caught = null;
        try {
            $validator->validateRequest($request);
            self::fail('Expected TypeMismatchError when non-numeric "abc" cannot be coerced to integer');
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('integer', $caught->params()['expected']);
        self::assertSame('abc', $caught->params()['actual']);
    }

    #[Test]
    public function with_coercion_invalid_boolean_string_rejected_in_strict_mode(): void
    {
        $spec = <<<'YAML'
openapi: 3.2.0
info:
  title: Boolean Coercion Cycle API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: listUsersByActiveFlag
      parameters:
        - name: active
          in: query
          required: true
          schema:
            type: boolean
      responses:
        '200':
          description: Filtered user payload echoing coerced active flag
          content:
            application/json:
              schema:
                type: object
                required:
                  - active
                properties:
                  active:
                    type: boolean
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->enableCoercion()
            ->build();

        $request = $this->factory->createServerRequest('GET', '/users?active=maybe');

        $caught = null;
        try {
            $validator->validateRequest($request);
            self::fail('Expected TypeMismatchError when non-boolean "maybe" is rejected by strict coercion');
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('boolean', $caught->params()['expected']);
        self::assertSame('maybe', $caught->params()['actual']);
    }

    #[Test]
    public function with_coercion_non_array_body_rejected_as_type_mismatch_not_min_items(): void
    {
        $spec = <<<'YAML'
openapi: 3.2.0
info:
  title: Array Body Coercion API
  version: 1.0.0
paths:
  /items:
    post:
      operationId: createItems
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: array
              items:
                type: string
              minItems: 1
      responses:
        '200':
          description: ack
          content:
            application/json:
              schema:
                type: object
                properties:
                  ok:
                    type: boolean
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->enableCoercion()
            ->build();

        $request = $this->factory->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('"hello"'));

        $caught = null;
        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException when non-array body "hello" is validated against type: array');
        } catch (ValidationException $exception) {
            foreach ($exception->getErrors() as $error) {
                if ($error instanceof TypeMismatchError) {
                    $caught = $error;
                    break;
                }
            }
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('array', $caught->params()['expected']);
    }
}
