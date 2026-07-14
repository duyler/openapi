<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\OpenApiValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use DateInterval;
use DateTimeInterface;

use function array_key_exists;
use function count;

final class CacheKeyCollisionTest extends TestCase
{
    private const string JSON_SPEC = <<<'JSON'
{
  "openapi": "3.2.0",
  "info": {
    "title": "Collision Test",
    "version": "1.0.0"
  },
  "paths": {}
}
JSON;

    private const string YAML_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Collision Test
  version: 1.0.0
paths: {}
YAML;

    #[Test]
    public function json_and_yaml_equivalent_specs_produce_different_cache_keys(): void
    {
        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->withCache($cache)
            ->build();

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML_SPEC)
            ->withCache($cache)
            ->build();

        $contentKeys = $pool->contentKeys();

        self::assertCount(2, $contentKeys, 'JSON and YAML specs with different raw strings must produce two distinct cache keys');
        self::assertNotSame($contentKeys[0], $contentKeys[1]);
    }

    #[Test]
    public function yaml_load_after_json_load_results_in_cache_miss_due_to_different_key(): void
    {
        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->withCache($cache)
            ->build();

        self::assertSame(1, $pool->missCount(), 'first JSON load should be a cache miss');
        self::assertSame(1, $pool->saveCount(), 'first JSON load should save the document');

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML_SPEC)
            ->withCache($cache)
            ->build();

        self::assertSame(2, $pool->missCount(), 'YAML load after JSON load must be a cache miss because raw strings differ');
        self::assertSame(2, $pool->saveCount(), 'YAML load must save a separate cache entry');
    }

    #[Test]
    public function loading_same_json_string_twice_uses_same_cache_key(): void
    {
        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->withCache($cache)
            ->build();

        OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->withCache($cache)
            ->build();

        $contentKeys = $pool->contentKeys();

        self::assertCount(1, $contentKeys, 'Same JSON content loaded twice must reuse the same cache key');
        self::assertSame(1, $pool->saveCount(), 'Same JSON content must trigger save only once (second load is cache hit)');
    }

    #[Test]
    public function loading_same_yaml_string_twice_uses_same_cache_key(): void
    {
        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML_SPEC)
            ->withCache($cache)
            ->build();

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML_SPEC)
            ->withCache($cache)
            ->build();

        $contentKeys = $pool->contentKeys();

        self::assertCount(1, $contentKeys, 'Same YAML content loaded twice must reuse the same cache key');
        self::assertSame(1, $pool->saveCount(), 'Same YAML content must trigger save only once (second load is cache hit)');
    }

    #[Test]
    public function same_content_string_loaded_as_json_and_yaml_shares_cache_key(): void
    {
        $sharedContent = '{"openapi":"3.2.0","info":{"title":"Shared","version":"1.0.0"},"paths":{}}';

        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromJsonString($sharedContent)
            ->withCache($cache)
            ->build();

        OpenApiValidatorBuilder::create()
            ->fromYamlString($sharedContent)
            ->withCache($cache)
            ->build();

        $contentKeys = $pool->contentKeys();

        self::assertCount(1, $contentKeys, 'Cache key is derived from raw content string, NOT from spec type — same string produces same key');
        self::assertSame(1, $pool->saveCount(), 'Second load with same content hits cache before YAML parser runs, save is called once');
    }

    #[Test]
    public function cached_json_document_is_returned_on_subsequent_json_load(): void
    {
        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        $validator1 = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->withCache($cache)
            ->build();

        $validator2 = OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->withCache($cache)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $validator1);
        self::assertInstanceOf(OpenApiValidator::class, $validator2);

        $originalDocument = $validator1->getDocument();
        $cachedDocument = $validator2->getDocument();

        self::assertNotNull($originalDocument);
        self::assertNotNull($cachedDocument);
        self::assertSame($originalDocument, $cachedDocument, 'Second build with identical content must return the exact same cached OpenApiDocument instance');
    }

    #[Test]
    public function cache_key_is_not_generated_when_cache_is_disabled(): void
    {
        $pool = new RecordingCachePool();

        OpenApiValidatorBuilder::create()
            ->fromJsonString(self::JSON_SPEC)
            ->build();

        self::assertSame(0, $pool->requestedCount(), 'Without withCache(), the pool is never consulted');
    }

    #[Test]
    public function invalid_yaml_does_not_pollute_cache_when_parsing_fails(): void
    {
        $pool = new RecordingCachePool();
        $cache = new SchemaCache($pool);

        $caught = null;
        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString('invalid: yaml: content:')
                ->withCache($cache)
                ->build();
        } catch (BuilderException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(0, $pool->saveCount(), 'Failed parsing must not persist anything to cache');
    }
}

final class RecordingCachePool implements CacheItemPoolInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, bool> */
    private array $savedFlag = [];

    /** @var list<string> */
    private array $requestedKeys = [];

    /** @var list<string> */
    private array $savedKeys = [];

    private int $missCount = 0;

    public function getItem(string $key): CacheItemInterface
    {
        $this->requestedKeys[] = $key;

        if (array_key_exists($key, $this->values)) {
            $hit = $this->savedFlag[$key] ?? false;
            $value = $this->values[$key];

            return new InMemoryCacheItem($key, $value, $hit);
        }

        $this->missCount++;
        $this->values[$key] = null;

        return new InMemoryCacheItem($key, null, false);
    }

    /**
     * @param list<string> $keys
     * @return array<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem($key);
        }

        return $result;
    }

    public function hasItem(string $key): bool
    {
        return ($this->savedFlag[$key] ?? false) && array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];
        $this->savedFlag = [];
        $this->requestedKeys = [];
        $this->savedKeys = [];
        $this->missCount = 0;

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->values[$key], $this->savedFlag[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key], $this->savedFlag[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        $this->values[$key] = $item->get();
        $this->savedFlag[$key] = true;
        $this->savedKeys[] = $key;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }

    public function missCount(): int
    {
        return $this->missCount;
    }

    public function saveCount(): int
    {
        $unique = [];
        foreach ($this->savedKeys as $key) {
            $unique[$key] = true;
        }

        return count($unique);
    }

    public function requestedCount(): int
    {
        return count($this->requestedKeys);
    }

    /**
     * @return list<string>
     */
    public function contentKeys(): array
    {
        $keys = [];
        foreach ($this->requestedKeys as $key) {
            if (str_starts_with($key, 'openapi_spec_content_')) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }
}

final class InMemoryCacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value,
        private readonly bool $hit,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        return $this;
    }
}
