<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Unit\Helper\ValidatorDependenciesAccessTrait;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WeakMap;

use function assert;

/** @internal */
final class FullCycleReuseTest extends TestCase
{
    use ValidatorDependenciesAccessTrait;

    private const string REUSE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Validator Reuse API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      operationId: getUserById
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: User resource
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/User'
components:
  schemas:
    User:
      type: object
      required:
        - id
        - name
      properties:
        id:
          type: string
        name:
          type: string
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function reset_between_cycles_produces_identical_operation_method_and_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REUSE_SPEC)
            ->build();

        $request1 = $this->factory->createServerRequest('GET', '/users/123');
        $operation1 = $validator->validateRequest($request1);

        $response1 = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"123","name":"Alice"}'));
        $validator->validateResponse($response1, $operation1);

        $validator->reset();

        $request2 = $this->factory->createServerRequest('GET', '/users/456');
        $operation2 = $validator->validateRequest($request2);

        $response2 = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"456","name":"Bob"}'));
        $validator->validateResponse($response2, $operation2);

        self::assertSame($operation1->method, $operation2->method);
        self::assertSame($operation1->path, $operation2->path);
        self::assertSame('GET', $operation2->method);
        self::assertSame('/users/{id}', $operation2->path);
        self::assertSame(1, $operation2->countPlaceholders());
    }

    #[Test]
    public function reset_between_cycles_clears_refresolver_cache(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REUSE_SPEC)
            ->build();

        $operation = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/123'));
        $response = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"123","name":"Alice"}'));
        $validator->validateResponse($response, $operation);

        $refResolver = self::readDependenciesProperty($validator, 'refResolver');
        $cacheBeforeReset = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheBeforeReset instanceof WeakMap);
        self::assertGreaterThan(0, $cacheBeforeReset->count());

        $validator->reset();

        $cacheAfterReset = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheAfterReset instanceof WeakMap);
        self::assertSame(0, $cacheAfterReset->count());
    }

    #[Test]
    public function without_reset_repeated_cycles_still_produce_identical_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REUSE_SPEC)
            ->build();

        $request1 = $this->factory->createServerRequest('GET', '/users/123');
        $operation1 = $validator->validateRequest($request1);
        $response1 = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"123","name":"Alice"}'));
        $validator->validateResponse($response1, $operation1);

        $request2 = $this->factory->createServerRequest('GET', '/users/456');
        $operation2 = $validator->validateRequest($request2);
        $response2 = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"456","name":"Bob"}'));
        $validator->validateResponse($response2, $operation2);

        self::assertSame($operation1->method, $operation2->method);
        self::assertSame($operation1->path, $operation2->path);
    }

    #[Test]
    public function without_reset_refresolver_cache_persists_proving_reset_mandatory_for_memory(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REUSE_SPEC)
            ->build();

        $refResolver = self::readDependenciesProperty($validator, 'refResolver');

        $operation = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/123'));
        $response = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"123","name":"Alice"}'));
        $validator->validateResponse($response, $operation);

        $cacheAfterCycle1 = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheAfterCycle1 instanceof WeakMap);
        $countAfterCycle1 = $cacheAfterCycle1->count();

        $operation2 = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/456'));
        $response2 = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"456","name":"Bob"}'));
        $validator->validateResponse($response2, $operation2);

        $cacheAfterCycle2 = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheAfterCycle2 instanceof WeakMap);
        $countAfterCycle2 = $cacheAfterCycle2->count();

        self::assertGreaterThanOrEqual($countAfterCycle1, $countAfterCycle2);
    }

    #[Test]
    public function reset_between_cycles_keeps_validation_outcomes_correct(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REUSE_SPEC)
            ->build();

        $operation = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/123'));
        $response = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"id":"123","name":"Alice"}'));
        $validator->validateResponse($response, $operation);

        $validator->reset();

        $invalidResponse = $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{}'));

        $operation2 = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/456'));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($invalidResponse, $operation2);
    }

    #[Test]
    public function concrete_validator_instance_remains_stable_after_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REUSE_SPEC)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);

        $operation1 = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/111'));
        $validator->reset();
        $operation2 = $validator->validateRequest($this->factory->createServerRequest('GET', '/users/222'));

        self::assertSame((string) $operation1, (string) $operation2);
    }
}
