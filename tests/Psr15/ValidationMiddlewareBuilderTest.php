<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Psr15;

use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Psr15\ValidationMiddleware;
use Duyler\OpenApi\Psr15\ValidationMiddlewareBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(ValidationMiddlewareBuilder::class)]
final class ValidationMiddlewareBuilderTest extends TestCase
{
    #[Test]
    public function create_builder(): void
    {
        $builder = new ValidationMiddlewareBuilder();

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function build_middleware_from_yaml_string(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Sample API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: A list of users
YAML;

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function build_middleware_from_json_string(): void
    {
        $json = <<<'JSON'
{
  "openapi": "3.0.3",
  "info": {
    "title": "Sample API",
    "version": "1.0.0"
  },
  "paths": {
    "/users": {
      "get": {
        "summary": "List users",
        "responses": {
          "200": {
            "description": "A list of users"
          }
        }
      }
    }
  }
}
JSON;

        $middleware = new ValidationMiddlewareBuilder()
            ->fromJsonString($json)
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function on_request_error_returns_new_instance(): void
    {
        $builder = new ValidationMiddlewareBuilder();
        $builder2 = $builder->onRequestError(function (): void {});

        $this->assertNotSame($builder, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function on_response_error_returns_new_instance(): void
    {
        $builder = new ValidationMiddlewareBuilder();
        $builder2 = $builder->onResponseError(function (): void {});

        $this->assertNotSame($builder, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function on_request_error_maintains_immutability(): void
    {
        $handler1 = function (): void {};
        $handler2 = function (): void {};

        $builder1 = new ValidationMiddlewareBuilder()->onRequestError($handler1);
        $builder2 = $builder1->onRequestError($handler2);

        $this->assertNotSame($builder1, $builder2);
    }

    #[Test]
    public function on_response_error_maintains_immutability(): void
    {
        $handler1 = function (): void {};
        $handler2 = function (): void {};

        $builder1 = new ValidationMiddlewareBuilder()->onResponseError($handler1);
        $builder2 = $builder1->onResponseError($handler2);

        $this->assertNotSame($builder1, $builder2);
    }

    #[Test]
    public function build_middleware_with_on_request_error_handler(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $requestErrorHandlerInvoked = false;
        $errorHandler = function ($e, $request) use (&$requestErrorHandlerInvoked): void {
            $requestErrorHandlerInvoked = true;
        };

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError($errorHandler)
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function build_middleware_with_on_response_error_handler(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $responseErrorHandlerInvoked = false;
        $errorHandler = function ($e, $request, $response) use (&$responseErrorHandlerInvoked): void {
            $responseErrorHandlerInvoked = true;
        };

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onResponseError($errorHandler)
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function build_middleware_with_both_error_handlers(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $requestErrorHandler = function (): void {};
        $responseErrorHandler = function (): void {};

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError($requestErrorHandler)
            ->onResponseError($responseErrorHandler)
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function chain_parent_methods_with_custom_handlers(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->enableNullableAsType()
            ->onRequestError(function (): void {})
            ->onResponseError(function (): void {});

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function build_middleware_creates_validator_from_parent(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: Success
YAML;

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $validatorProperty = $reflection->getProperty('validator');

        $validator = $validatorProperty->getValue($middleware);

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
    }

    #[Test]
    public function build_middleware_from_yaml_file(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_openapi.yaml';
        file_put_contents($tempFile, "openapi: 3.0.3\ninfo:\n  title: File Test\n  version: 1.0.0\npaths: []");

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlFile($tempFile)
            ->buildMiddleware();

        unlink($tempFile);

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function build_middleware_from_json_file(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_openapi.json';
        file_put_contents($tempFile, '{"openapi":"3.0.3","info":{"title":"JSON Test","version":"1.0.0"},"paths":{}}');

        $middleware = new ValidationMiddlewareBuilder()
            ->fromJsonFile($tempFile)
            ->buildMiddleware();

        unlink($tempFile);

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function constructor_accepts_all_parent_parameters(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = new ValidationMiddlewareBuilder(
            specContent: $yaml,
            specType: 'yaml',
            coercion: true,
            nullableAsType: true,
        );

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function constructor_accepts_error_handlers(): void
    {
        $onRequestError = function (): void {};
        $onResponseError = function (): void {};

        $builder = new ValidationMiddlewareBuilder(
            onRequestError: $onRequestError,
            onResponseError: $onResponseError,
        );

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function constructor_has_null_default_for_error_handlers(): void
    {
        $builder = new ValidationMiddlewareBuilder();

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function build_middleware_with_all_parent_options(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->enableNullableAsType()
            ->onRequestError(function (): void {})
            ->onResponseError(function (): void {})
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function multiple_builder_instances_are_independent(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $handler1 = function (): void {};
        $handler2 = function (): void {};

        $builder1 = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError($handler1);

        $builder2 = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError($handler2);

        $middleware1 = $builder1->buildMiddleware();
        $middleware2 = $builder2->buildMiddleware();

        $this->assertNotSame($middleware1, $middleware2);
    }

    #[Test]
    public function on_request_error_can_be_called_multiple_times(): void
    {
        $builder = new ValidationMiddlewareBuilder();

        $handler1 = function (): void {};
        $handler2 = function (): void {};
        $handler3 = function (): void {};

        $builder2 = $builder->onRequestError($handler1);
        $builder3 = $builder2->onRequestError($handler2);
        $builder4 = $builder3->onRequestError($handler3);

        $this->assertNotSame($builder, $builder2);
        $this->assertNotSame($builder2, $builder3);
        $this->assertNotSame($builder3, $builder4);
    }

    #[Test]
    public function on_response_error_can_be_called_multiple_times(): void
    {
        $builder = new ValidationMiddlewareBuilder();

        $handler1 = function (): void {};
        $handler2 = function (): void {};
        $handler3 = function (): void {};

        $builder2 = $builder->onResponseError($handler1);
        $builder3 = $builder2->onResponseError($handler2);
        $builder4 = $builder3->onResponseError($handler3);

        $this->assertNotSame($builder, $builder2);
        $this->assertNotSame($builder2, $builder3);
        $this->assertNotSame($builder3, $builder4);
    }

    #[Test]
    public function parent_methods_work_correctly(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->fromJsonString('{"openapi":"3.0.3","info":{"title":"Test2","version":"1.0.0"},"paths":{}}')
            ->enableCoercion()
            ->enableNullableAsType();

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function immutable_pattern_not_broken_by_parent_methods(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder1 = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError(function (): void {});

        $builder2 = $builder1
            ->enableCoercion()
            ->onResponseError(function (): void {});

        $builder3 = $builder2
            ->enableNullableAsType()
            ->onRequestError(function (): void {});

        $this->assertNotSame($builder1, $builder2);
        $this->assertNotSame($builder2, $builder3);
    }

    #[Test]
    public function validator_created_only_once_on_build_middleware(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $validatorProperty = $reflection->getProperty('validator');

        $validator = $validatorProperty->getValue($middleware);

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function error_handlers_are_passed_to_middleware(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $requestErrorHandler = function (): void {};
        $responseErrorHandler = function (): void {};

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError($requestErrorHandler)
            ->onResponseError($responseErrorHandler)
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $onRequestErrorProperty = $reflection->getProperty('onRequestError');
        $onResponseErrorProperty = $reflection->getProperty('onResponseError');

        $this->assertSame($requestErrorHandler, $onRequestErrorProperty->getValue($middleware));
        $this->assertSame($responseErrorHandler, $onResponseErrorProperty->getValue($middleware));
    }

    #[Test]
    public function null_error_handlers_are_passed_correctly(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $onRequestErrorProperty = $reflection->getProperty('onRequestError');
        $onResponseErrorProperty = $reflection->getProperty('onResponseError');

        $this->assertNull($onRequestErrorProperty->getValue($middleware));
        $this->assertNull($onResponseErrorProperty->getValue($middleware));
    }

    #[Test]
    public function end_to_end_middleware_creation(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: array
                items:
                  type: object
                  properties:
                    id:
                      type: integer
                    name:
                      type: string
YAML;

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->enableNullableAsType()
            ->onRequestError(function (): void {})
            ->onResponseError(function (): void {})
            ->buildMiddleware();

        $this->assertInstanceOf(ValidationMiddleware::class, $middleware);
    }

    #[Test]
    public function inheritance_from_parent_works_correctly(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = new ValidationMiddlewareBuilder();

        $this->assertTrue(method_exists($builder, 'fromYamlFile'));
        $this->assertTrue(method_exists($builder, 'fromJsonFile'));
        $this->assertTrue(method_exists($builder, 'fromYamlString'));
        $this->assertTrue(method_exists($builder, 'fromJsonString'));
        $this->assertTrue(method_exists($builder, 'enableCoercion'));
        $this->assertTrue(method_exists($builder, 'enableNullableAsType'));
        $this->assertTrue(method_exists($builder, 'build'));
        $this->assertTrue(method_exists($builder, 'buildMiddleware'));
        $this->assertTrue(method_exists($builder, 'onRequestError'));
        $this->assertTrue(method_exists($builder, 'onResponseError'));
    }

    #[Test]
    public function validator_from_parent_has_correct_document(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: API Title
  version: 2.0.0
paths:
  /test:
    get:
      summary: Test endpoint
      responses:
        '200':
          description: OK
YAML;

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $validatorProperty = $reflection->getProperty('validator');

        $validator = $validatorProperty->getValue($middleware);

        $this->assertSame('API Title', $validator->document->info->title);
        $this->assertSame('2.0.0', $validator->document->info->version);
    }

    #[Test]
    public function from_yaml_file_returns_builder_with_yaml_spec(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_openapi.yaml';
        file_put_contents($tempFile, "openapi: 3.0.3\ninfo:\n  title: YAML File\n  version: 1.0.0\npaths: []");

        $builder = new ValidationMiddlewareBuilder()->fromYamlFile($tempFile);

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);

        unlink($tempFile);
    }

    #[Test]
    public function from_json_file_returns_builder_with_json_spec(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_openapi.json';
        file_put_contents($tempFile, '{"openapi":"3.0.3","info":{"title":"JSON File","version":"1.0.0"},"paths":{}}');

        $builder = new ValidationMiddlewareBuilder()->fromJsonFile($tempFile);

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);

        unlink($tempFile);
    }

    #[Test]
    public function from_yaml_string_returns_builder_with_yaml_spec(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: YAML String\n  version: 1.0.0\npaths: []";

        $builder = new ValidationMiddlewareBuilder()->fromYamlString($yaml);

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function from_json_string_returns_builder_with_json_spec(): void
    {
        $json = '{"openapi":"3.0.3","info":{"title":"JSON String","version":"1.0.0"},"paths":{}}';

        $builder = new ValidationMiddlewareBuilder()->fromJsonString($json);

        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder);
    }

    #[Test]
    public function with_validator_pool_returns_new_instance(): void
    {
        $pool = new ValidatorPool();
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->withValidatorPool($pool);

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function with_cache_returns_new_instance(): void
    {
        $cacheItem = $this->createMock(CacheItemPoolInterface::class);
        $cache = new SchemaCache($cacheItem);
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->withCache($cache);

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function with_logger_returns_new_instance(): void
    {
        $logger = new class {};
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->withLogger($logger);

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function with_error_formatter_returns_new_instance(): void
    {
        $formatter = new DetailedFormatter();
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->withErrorFormatter($formatter);

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function enable_coercion_returns_new_instance(): void
    {
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->enableCoercion();

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function enable_nullable_as_type_returns_new_instance(): void
    {
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->enableNullableAsType();

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function with_event_dispatcher_returns_new_instance(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }

            public function listen(object $listener): void {}
        };
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->withEventDispatcher($dispatcher);

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function with_format_returns_new_instance(): void
    {
        $validator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };
        $builder1 = new ValidationMiddlewareBuilder();
        $builder2 = $builder1->withFormat('string', 'custom', $validator);

        $this->assertNotSame($builder1, $builder2);
        $this->assertInstanceOf(ValidationMiddlewareBuilder::class, $builder2);
    }

    #[Test]
    public function builder_preserves_on_request_error_through_chain(): void
    {
        $handler = function (): void {};
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onRequestError($handler)
            ->enableCoercion()
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $onRequestErrorProperty = $reflection->getProperty('onRequestError');

        $this->assertSame($handler, $onRequestErrorProperty->getValue($middleware));
    }

    #[Test]
    public function builder_preserves_on_response_error_through_chain(): void
    {
        $handler = function (): void {};
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $middleware = new ValidationMiddlewareBuilder()
            ->fromYamlString($yaml)
            ->onResponseError($handler)
            ->enableNullableAsType()
            ->buildMiddleware();

        $reflection = new ReflectionClass($middleware);
        $onResponseErrorProperty = $reflection->getProperty('onResponseError');

        $this->assertSame($handler, $onResponseErrorProperty->getValue($middleware));
    }
}
