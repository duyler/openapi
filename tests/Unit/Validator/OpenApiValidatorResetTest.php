<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WeakMap;

use stdClass;

use function assert;

/** @internal */
final class OpenApiValidatorResetTest extends TestCase
{
    private const string YAML = <<<YAML
openapi: 3.2.0
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
                \$ref: '#/components/schemas/User'
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
      required:
        - id
        - name
    PositiveInt:
      type: integer
      minimum: 0
YAML;

    #[Test]
    public function schema_validator_is_created_once_in_constructor(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $schemaValidator = self::readProperty($validator, OpenApiValidator::class, 'schemaValidator');

        $this->assertInstanceOf(SchemaValidator::class, $schemaValidator);
    }

    #[Test]
    public function schema_validator_is_reused_across_validate_schema_calls(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $schemaValidatorBefore = self::readProperty($validator, OpenApiValidator::class, 'schemaValidator');

        $validator->validateSchema(['id' => '1', 'name' => 'Test'], '#/components/schemas/User');

        $schemaValidatorAfter = self::readProperty($validator, OpenApiValidator::class, 'schemaValidator');

        $this->assertSame($schemaValidatorBefore, $schemaValidatorAfter, 'SchemaValidator should be the same instance after validateSchema() call');
    }

    #[Test]
    public function reset_clears_validator_pool(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $pool = $validator->pool;

        $callCount = 0;
        $factory = function () use (&$callCount) {
            ++$callCount;

            return new stdClass();
        };

        $pool->getOrCreate('test_key', $factory);
        $pool->getOrCreate('test_key', $factory);

        $this->assertSame(1, $callCount);

        $validator->reset();

        $pool->getOrCreate('test_key', $factory);

        $this->assertSame(2, $callCount, 'After reset(), pool should have been cleared');
    }

    #[Test]
    public function reset_clears_ref_resolver_cache(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $refResolver = self::readProperty($validator, OpenApiValidator::class, 'refResolver');

        $factory = new Psr17Factory();

        $request = $factory->createServerRequest('GET', '/users/123');
        $operation = $validator->validateRequest($request);

        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":"123","name":"Test"}'));

        $validator->validateResponse($response, $operation);

        $cacheBefore = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheBefore instanceof WeakMap);

        $this->assertGreaterThan(0, $cacheBefore->count(), 'Cache should contain entries after validation');

        $validator->reset();

        $cacheAfter = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheAfter instanceof WeakMap);

        $this->assertSame(0, $cacheAfter->count(), 'After reset(), RefResolver cache should be empty');
    }

    #[Test]
    public function validation_works_after_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $validator->validateSchema(5, '#/components/schemas/PositiveInt');

        $validator->reset();

        $validator->validateSchema(10, '#/components/schemas/PositiveInt');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validation_fails_correctly_after_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $validator->validateSchema(['id' => '1', 'name' => 'Test'], '#/components/schemas/User');

        $validator->reset();

        $this->expectException(ValidationException::class);

        $validator->validateSchema(['age' => 30], '#/components/schemas/User');
    }

    #[Test]
    public function request_response_validation_works_after_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $factory = new Psr17Factory();

        $request = $factory->createServerRequest('GET', '/users/123');
        $operation = $validator->validateRequest($request);

        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":"123","name":"Test"}'));

        $validator->validateResponse($response, $operation);

        $validator->reset();

        $request2 = $factory->createServerRequest('GET', '/users/456');
        $operation2 = $validator->validateRequest($request2);

        $response2 = $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":"456","name":"Another"}'));

        $validator->validateResponse($response2, $operation2);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function schema_validator_remains_same_after_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $schemaValidatorBefore = self::readProperty($validator, OpenApiValidator::class, 'schemaValidator');

        $validator->reset();

        $schemaValidatorAfter = self::readProperty($validator, OpenApiValidator::class, 'schemaValidator');

        $this->assertSame($schemaValidatorBefore, $schemaValidatorAfter, 'SchemaValidator should remain the same instance after reset()');
    }

    #[Test]
    public function pool_and_ref_resolver_instances_remain_same_after_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $poolBefore = $validator->pool;
        $refResolverBefore = self::readProperty($validator, OpenApiValidator::class, 'refResolver');

        $validator->reset();

        $poolAfter = $validator->pool;
        $refResolverAfter = self::readProperty($validator, OpenApiValidator::class, 'refResolver');

        $this->assertSame($poolBefore, $poolAfter, 'Pool instance should remain the same after reset()');
        $this->assertSame($refResolverBefore, $refResolverAfter, 'RefResolver instance should remain the same after reset()');
    }

    #[Test]
    public function reset_on_fresh_validator_is_noop(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $validator->reset();

        $validator->validateSchema(5, '#/components/schemas/PositiveInt');

        $this->expectNotToPerformAssertions();
    }

    private static function readProperty(object $object, string $class, string $property): object
    {
        $prop = new ReflectionProperty($class, $property);

        return $prop->getValue($object);
    }
}
