<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

final class ConcurrentValidationTest extends TestCase
{
    private const string CONCURRENT_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Concurrent Validation API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: listUsers
      responses:
        '200':
          description: User list
    post:
      operationId: createUser
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
                  minLength: 1
      responses:
        '201':
          description: User created
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - title
              properties:
                title:
                  type: string
                  minLength: 1
      responses:
        '201':
          description: Item created
  /search:
    get:
      operationId: searchByLimit
      parameters:
        - name: limit
          in: query
          required: true
          schema:
            type: integer
            minimum: 1
            maximum: 100
      responses:
        '200':
          description: Search results
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function sequential_distinct_requests_return_expected_operations_without_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->build();

        $request1 = $this->factory->createServerRequest('GET', '/users');
        $operation1 = $validator->validateRequest($request1);

        $request2 = $this->factory
            ->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['title' => 'Widget'])));
        $operation2 = $validator->validateRequest($request2);

        $request3 = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => 'Alice'])));
        $operation3 = $validator->validateRequest($request3);

        $this->assertOperationEquals('GET', '/users', $operation1);
        $this->assertOperationEquals('POST', '/items', $operation2);
        $this->assertOperationEquals('POST', '/users', $operation3);
    }

    #[Test]
    public function error_in_first_request_does_not_leak_into_second_valid_request(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->build();

        $failingRequest = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => ''])));

        try {
            $validator->validateRequest($failingRequest);
            self::fail('First request must fail validation with empty name');
        } catch (ValidationException $e) {
            $this->assertSame('/name', $e->getErrors()[0]->dataPath());
        }

        $validRequest = $this->factory
            ->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['title' => 'Valid Item'])));

        $operation = $validator->validateRequest($validRequest);

        $this->assertOperationEquals('POST', '/items', $operation);
    }

    #[Test]
    public function validation_errors_do_not_accumulate_between_failing_requests(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->build();

        $firstFailing = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => ''])));

        try {
            $validator->validateRequest($firstFailing);
            self::fail('First request must fail validation with empty name');
        } catch (ValidationException $e) {
            $this->assertSame('/name', $e->getErrors()[0]->dataPath());
        }

        $secondFailing = $this->factory
            ->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['title' => ''])));

        try {
            $validator->validateRequest($secondFailing);
            self::fail('Second request must fail validation with empty title');
        } catch (ValidationException $e) {
            $this->assertSame(
                '/title',
                $e->getErrors()[0]->dataPath(),
                'Second request error must reference /title, not /name from the previous request',
            );
        }
    }

    #[Test]
    public function coerced_attributes_do_not_leak_between_requests(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->enableCoercion()
            ->build();

        $requestWithCoercion = $this->factory->createServerRequest('GET', '/search?limit=5');
        $operation1 = $validator->validateRequest($requestWithCoercion);
        $this->assertOperationEquals('GET', '/search', $operation1);

        $requestWithDifferentValue = $this->factory->createServerRequest('GET', '/search?limit=42');
        $operation2 = $validator->validateRequest($requestWithDifferentValue);
        $this->assertOperationEquals('GET', '/search', $operation2);

        $requestWithBoundary = $this->factory->createServerRequest('GET', '/search?limit=100');
        $operation3 = $validator->validateRequest($requestWithBoundary);
        $this->assertOperationEquals('GET', '/search', $operation3);
    }

    #[Test]
    public function coercion_failure_in_first_request_does_not_break_second_request(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->enableCoercion()
            ->build();

        $nonCoercibleRequest = $this->factory->createServerRequest('GET', '/search?limit=abc');

        try {
            $validator->validateRequest($nonCoercibleRequest);
            self::fail('Non-numeric "abc" must not coerce to integer');
        } catch (TypeMismatchError $e) {
            $this->assertSame('integer', $e->params()['expected']);
        }

        $coercibleRequest = $this->factory->createServerRequest('GET', '/search?limit=10');
        $operation = $validator->validateRequest($coercibleRequest);

        $this->assertOperationEquals('GET', '/search', $operation);
    }

    #[Test]
    public function operation_context_is_rebuilt_per_request_not_reused(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->build();

        $operation1 = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/users'),
        );

        $operation2 = $validator->validateRequest(
            $this->factory
                ->createServerRequest('POST', '/items')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->factory->createStream(json_encode(['title' => 'X']))),
        );

        $this->assertNotSame($operation1->path, $operation2->path);
        $this->assertNotSame($operation1->method, $operation2->method);
        $this->assertNotSame((string) $operation1, (string) $operation2);
    }

    #[Test]
    public function mixed_success_and_failure_sequence_isolates_each_request(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CONCURRENT_SPEC)
            ->build();

        $operation1 = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/users'),
        );
        $this->assertOperationEquals('GET', '/users', $operation1);

        $itemsInvalid = $this->factory
            ->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['title' => ''])));
        try {
            $validator->validateRequest($itemsInvalid);
            self::fail('Empty title must fail minLength validation');
        } catch (ValidationException $e) {
            $this->assertSame('/title', $e->getErrors()[0]->dataPath());
        }

        $operation3 = $validator->validateRequest(
            $this->factory
                ->createServerRequest('POST', '/users')
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->factory->createStream(json_encode(['name' => 'Bob']))),
        );
        $this->assertOperationEquals('POST', '/users', $operation3);

        $usersInvalid = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['name' => ''])));
        try {
            $validator->validateRequest($usersInvalid);
            self::fail('Empty name must fail minLength validation');
        } catch (ValidationException $e) {
            $this->assertSame('/name', $e->getErrors()[0]->dataPath());
        }

        $operation5 = $validator->validateRequest(
            $this->factory->createServerRequest('GET', '/users'),
        );
        $this->assertOperationEquals('GET', '/users', $operation5);
    }

    private function assertOperationEquals(string $method, string $path, Operation $operation): void
    {
        $this->assertSame($method, $operation->method);
        $this->assertSame($path, $operation->path);
    }
}
