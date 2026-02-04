<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;

final class OpenApiValidatorBuilderTest extends TestCase
{
    #[Test]
    public function create_builder(): void
    {
        $builder = OpenApiValidatorBuilder::create();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function build_from_yaml_string(): void
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

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $this->assertSame('Sample API', $validator->document->info->title);
    }

    #[Test]
    public function build_from_json_string(): void
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

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->build();

        $this->assertSame('Sample API', $validator->document->info->title);
    }

    #[Test]
    public function throw_error_when_no_spec_loaded(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec not loaded');

        OpenApiValidatorBuilder::create()
            ->build();
    }

    #[Test]
    public function return_new_instance_on_each_call(): void
    {
        $builder = OpenApiValidatorBuilder::create();
        $builder2 = $builder->fromYamlString("openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []");

        $this->assertNotSame($builder, $builder2);
    }

    #[Test]
    public function chain_multiple_methods(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->enableNullableAsType();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_validator_pool(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $pool = new ValidatorPool();

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withValidatorPool($pool);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_cache(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $cache = new SchemaCache($this->createMock(CacheItemPoolInterface::class));

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withCache($cache);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_logger(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $logger = new class {};

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withLogger($logger);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function use_custom_error_formatter(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $formatter = new DetailedFormatter();

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withErrorFormatter($formatter);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function register_custom_format(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $customValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withFormat('string', 'custom-format', $customValidator);

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function enable_coercion(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function enable_nullable_as_type(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableNullableAsType();

        $this->assertInstanceOf(OpenApiValidatorBuilder::class, $builder);
    }

    #[Test]
    public function build_with_all_options(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();

        $customValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withValidatorPool($pool)
            ->withErrorFormatter($formatter)
            ->withFormat('string', 'custom', $customValidator)
            ->enableCoercion()
            ->build();

        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function maintain_immutability_with_multiple_with_calls(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $builder1 = OpenApiValidatorBuilder::create()->fromYamlString($yaml);
        $builder2 = $builder1->enableCoercion();
        $builder3 = $builder2->withLogger(new class {});

        $this->assertNotSame($builder1, $builder2);
        $this->assertNotSame($builder2, $builder3);
    }

    #[Test]
    public function build_with_custom_formatter(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $formatter = new DetailedFormatter();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withErrorFormatter($formatter)
            ->build();

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function build_with_custom_dispatcher(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }

            public function listen(object $listener): void {}
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function build_with_custom_validator_pool(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $pool = new ValidatorPool();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withValidatorPool($pool)
            ->build();

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function build_with_cache_enabled(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturn($cacheItem);
        $pool
            ->method('save')
            ->willReturn(true);

        $cache = new SchemaCache($pool);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withCache($cache)
            ->build();

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function build_with_cache_disabled(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator);
        self::assertNull($validator->cache);
    }

    #[Test]
    public function build_with_schema_from_file(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_openapi.yaml';
        file_put_contents($tempFile, "openapi: 3.0.3\ninfo:\n  title: File Test\n  version: 1.0.0\npaths: []");

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempFile)
            ->build();

        unlink($tempFile);

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('File Test', $validator->document->info->title);
    }

    #[Test]
    public function build_throws_exception_for_invalid_yaml(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Failed to parse spec');

        OpenApiValidatorBuilder::create()
            ->fromYamlString('invalid: yaml: content:')
            ->build();
    }

    #[Test]
    public function build_throws_exception_for_invalid_json(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Failed to parse spec');

        OpenApiValidatorBuilder::create()
            ->fromJsonString('{"invalid": json}')
            ->build();
    }

    #[Test]
    public function build_throws_exception_for_nonexistent_file(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec file does not exist');

        OpenApiValidatorBuilder::create()
            ->fromYamlFile('/nonexistent/file.yaml')
            ->build();
    }

    #[Test]
    public function build_with_multiple_formats(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $validator1 = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };

        $validator2 = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withFormat('string', 'custom1', $validator1)
            ->withFormat('integer', 'custom2', $validator2)
            ->build();

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
    }

    #[Test]
    public function build_preserves_all_configuration(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $logger = new class {};

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

        $cachePool = $this->createMock(CacheItemPoolInterface::class);
        $cachePool
            ->method('getItem')
            ->willReturn($cacheItem);
        $cachePool
            ->method('save')
            ->willReturn(true);

        $cache = new SchemaCache($cachePool);

        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }

            public function listen(object $listener): void {}
        };

        $customValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withValidatorPool($pool)
            ->withErrorFormatter($formatter)
            ->withLogger($logger)
            ->withCache($cache)
            ->withEventDispatcher($dispatcher)
            ->withFormat('string', 'custom', $customValidator)
            ->enableCoercion()
            ->enableNullableAsType()
            ->build();

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function fromJsonFile_loads_file(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_openapi.json';
        file_put_contents($tempFile, '{"openapi":"3.0.3","info":{"title":"JSON Test","version":"1.0.0"},"paths":{}}');

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonFile($tempFile)
            ->build();

        unlink($tempFile);

        $this->assertInstanceOf(OpenApiValidator::class, $validator);
        $this->assertSame('JSON Test', $validator->document->info->title);
    }

    #[Test]
    public function withEventDispatcher_returns_new_builder(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []";

        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }

            public function listen(object $listener): void {}
        };

        $builder1 = OpenApiValidatorBuilder::create()->fromYamlString($yaml);
        $builder2 = $builder1->withEventDispatcher($dispatcher);

        $this->assertNotSame($builder1, $builder2);
    }

    #[Test]
    public function build_uses_cache_from_file(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_cache.yaml';
        file_put_contents($tempFile, "openapi: 3.0.3\ninfo:\n  title: Cached\n  version: 1.0.0\npaths: []");

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn(new OpenApiDocument(
                openapi: '3.0.3',
                info: new InfoObject(title: 'From Cache', version: '1.0.0'),
            ));

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempFile)
            ->withCache($cache)
            ->build();

        unlink($tempFile);

        $this->assertSame('From Cache', $validator->document->info->title);
    }

    #[Test]
    public function build_uses_cache_from_string(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Original\n  version: 1.0.0\npaths: []";

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn(new OpenApiDocument(
                openapi: '3.0.3',
                info: new InfoObject(title: 'From Cache', version: '1.0.0'),
            ));

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withCache($cache)
            ->build();

        $this->assertSame('From Cache', $validator->document->info->title);
    }

    #[Test]
    public function build_with_json_file_and_cache(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_json_cache.json';
        file_put_contents($tempFile, '{"openapi":"3.0.3","info":{"title":"JSON Cached","version":"1.0.0"},"paths":{}}');

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn(new OpenApiDocument(
                openapi: '3.0.3',
                info: new InfoObject(title: 'From JSON Cache', version: '1.0.0'),
            ));

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonFile($tempFile)
            ->withCache($cache)
            ->build();

        unlink($tempFile);

        $this->assertSame('From JSON Cache', $validator->document->info->title);
    }

    #[Test]
    public function build_with_real_file_path_generates_cache_key(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_realpath.yaml';
        file_put_contents($tempFile, "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths: []");

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);
        $cacheItem
            ->method('set')
            ->willReturnSelf();
        $cacheItem
            ->method('expiresAfter')
            ->willReturnSelf();

        $pool = $this->createMock(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturn($cacheItem);
        $pool
            ->method('save')
            ->willReturn(true);

        $cache = new SchemaCache($pool);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($tempFile)
            ->withCache($cache)
            ->build();

        unlink($tempFile);

        $this->assertSame('Test', $validator->document->info->title);
    }

    #[Test]
    public function build_with_nonexistent_file_generates_cache_key(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec file does not exist');

        OpenApiValidatorBuilder::create()
            ->fromYamlFile('/nonexistent/path/file.yaml')
            ->build();
    }
}
