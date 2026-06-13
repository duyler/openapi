<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use Duyler\OpenApi\Validator\Validation\ValidationContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WeakMap;
use Duyler\OpenApi\Test\Unit\Helper\ValidatorDependenciesAccessTrait;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;

use function assert;

/** @internal */
final class RefResolverSharedInstanceTest extends TestCase
{
    use ValidatorDependenciesAccessTrait;
    private const string YAML = <<<YAML
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
YAML;

    #[Test]
    public function ref_resolver_is_shared_between_request_and_response_validators(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $mainRefResolver = self::readDependenciesProperty($validator, 'refResolver');
        $requestRefResolver = self::readRequestBodyRefResolver($validator);
        $responseRefResolver = self::readResponseRefResolver($validator);

        $this->assertInstanceOf(RefResolver::class, $mainRefResolver);
        $this->assertInstanceOf(RefResolver::class, $requestRefResolver);
        $this->assertInstanceOf(RefResolver::class, $responseRefResolver);

        $this->assertSame($mainRefResolver, $requestRefResolver, 'RequestBodyValidatorWithContext should share RefResolver with OpenApiValidator');
        $this->assertSame($mainRefResolver, $responseRefResolver, 'ResponseValidatorWithContext should share RefResolver with OpenApiValidator');
        $this->assertSame($requestRefResolver, $responseRefResolver, 'Request and Response validators should share the same RefResolver');
    }

    #[Test]
    public function only_one_ref_resolver_instance_exists(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML)
            ->build();

        $main = self::readDependenciesProperty($validator, 'refResolver');
        $request = self::readRequestBodyRefResolver($validator);
        $response = self::readResponseRefResolver($validator);

        $ids = [
            spl_object_id($main),
            spl_object_id($request),
            spl_object_id($response),
        ];

        $this->assertCount(1, array_unique($ids), 'Exactly 1 unique RefResolver instance should exist');
    }

    #[Test]
    public function ref_resolver_weakmap_cache_is_shared_after_validation(): void
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

        $refResolver = self::readDependenciesProperty($validator, 'refResolver');
        $cache = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cache instanceof WeakMap);

        $this->assertGreaterThan(0, $cache->count(), 'WeakMap cache should contain entries after response validation');

        $cacheCountAfterFirst = $cache->count();

        $response2 = $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":"456","name":"Another"}'));

        $validator->validateResponse($response2, $operation);

        $cacheAfterSecond = self::readProperty($refResolver, RefResolver::class, 'cache');
        assert($cacheAfterSecond instanceof WeakMap);

        $this->assertSame($cacheCountAfterFirst, $cacheAfterSecond->count(), 'Cache should be reused across multiple response validations');
    }

    private static function readRequestBodyRefResolver(OpenApiValidator $validator): RefResolver
    {
        $requestValidation = self::readDependenciesProperty($validator, 'requestValidation');
        $context = self::readProperty($requestValidation, RequestValidationHandler::class, 'context');
        $requestValidator = self::readProperty($context, ValidationContext::class, 'requestValidator');
        $bodyValidator = self::readProperty($requestValidator, RequestValidator::class, 'bodyValidator');
        $dependencies = self::readProperty($bodyValidator, RequestBodyValidatorWithContext::class, 'dependencies');

        return self::readProperty($dependencies, SchemaValidatorDependencies::class, 'refResolver');
    }

    private static function readResponseRefResolver(OpenApiValidator $validator): RefResolver
    {
        $responseValidation = self::readDependenciesProperty($validator, 'responseValidation');
        $context = self::readProperty($responseValidation, ResponseValidationHandler::class, 'context');
        $responseValidator = self::readProperty($context, ValidationContext::class, 'responseValidator');
        $dependencies = self::readProperty($responseValidator, ResponseValidatorWithContext::class, 'dependencies');

        return self::readProperty($dependencies, SchemaValidatorDependencies::class, 'refResolver');
    }
}
