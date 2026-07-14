<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Cache;

use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;

use function array_key_exists;

final class SchemaCacheTest extends TestCase
{
    #[Test]
    public function get_returns_cached_document(): void
    {
        $pool = $this->createMockCachePool();
        $document = $this->createDocument();

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn($document);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);
        $result = $cache->get('test_key');

        self::assertSame($document, $result);
    }

    #[Test]
    public function get_returns_null_when_cache_miss(): void
    {
        $pool = $this->createMockCachePool();

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);
        $cacheItem
            ->method('get')
            ->willReturn(null);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);
        $result = $cache->get('test_key');

        self::assertNull($result);
    }

    #[Test]
    public function get_returns_null_when_cached_value_is_not_document(): void
    {
        $pool = $this->createMockCachePool();

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn('invalid_value');

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);
        $result = $cache->get('test_key');

        self::assertNull($result);
    }

    #[Test]
    public function set_saves_document_to_cache(): void
    {
        $pool = $this->createMockCachePool();
        $document = $this->createDocument();

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($document)
            ->willReturnSelf();
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturnSelf();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new SchemaCache($pool, 3600);
        $cache->set('test_key', $document);
    }

    #[Test]
    public function delete_removes_document_from_cache(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('deleteItem')
            ->with('test_key');

        $cache = new SchemaCache($pool);
        $cache->delete('test_key');
    }

    #[Test]
    public function clear_clears_all_cache(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('clear');

        $cache = new SchemaCache($pool);
        $cache->clear();
    }

    #[Test]
    public function has_returns_true_when_item_exists(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('hasItem')
            ->with('test_key')
            ->willReturn(true);

        $cache = new SchemaCache($pool);
        $result = $cache->has('test_key');

        self::assertTrue($result);
    }

    #[Test]
    public function has_returns_false_when_item_not_exists(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('hasItem')
            ->with('test_key')
            ->willReturn(false);

        $cache = new SchemaCache($pool);
        $result = $cache->has('test_key');

        self::assertFalse($result);
    }

    #[Test]
    public function set_uses_custom_ttl_when_provided(): void
    {
        $pool = $this->createMockCachePool();
        $document = $this->createDocument();

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($document)
            ->willReturn($cacheItem);
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(7200)
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new SchemaCache($pool, 7200);
        $cache->set('test_key', $document);
    }

    #[Test]
    public function set_uses_default_ttl_when_not_provided(): void
    {
        $pool = $this->createMockCachePool();
        $document = $this->createDocument();

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($document)
            ->willReturn($cacheItem);
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(3600)
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new SchemaCache($pool);
        $cache->set('test_key', $document);
    }

    /**
     * CA-01: Multiple keys are stored and retrieved independently.
     *
     * Verifies that storing two documents under different keys does not
     * interfere — each key retrieves exactly the document stored under it.
     */
    #[Test]
    public function multiple_keys_are_stored_and_retrieved_independently(): void
    {
        $storage = [];

        $pool = $this->createStub(CacheItemPoolInterface::class);

        $pool
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$storage) {
                $item = $this->createStub(CacheItemInterface::class);
                $item->method('isHit')->willReturn(array_key_exists($key, $storage));
                $item->method('get')->willReturn($storage[$key] ?? null);
                $item->method('set')->willReturnCallback(function ($value) use ($key, &$storage, $item) {
                    $storage[$key] = $value;

                    return $item;
                });
                $item->method('expiresAfter')->willReturnSelf();

                return $item;
            });

        $pool->method('save')->willReturn(true);

        $cache = new SchemaCache($pool);
        $doc1 = $this->createDocumentWithTitle('API v1');
        $doc2 = $this->createDocumentWithTitle('API v2');

        $cache->set('spec.v1', $doc1);
        $cache->set('spec.v2', $doc2);

        $retrieved1 = $cache->get('spec.v1');
        $retrieved2 = $cache->get('spec.v2');

        self::assertSame($doc1, $retrieved1);
        self::assertSame($doc2, $retrieved2);
        self::assertNotSame($retrieved1, $retrieved2);
    }

    /**
     * CA-01: A miss on one key does not affect another key's hit.
     */
    #[Test]
    public function miss_on_one_key_does_not_affect_another_key(): void
    {
        $storage = [];

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$storage) {
                $item = $this->createStub(CacheItemInterface::class);
                $item->method('isHit')->willReturn(array_key_exists($key, $storage));
                $item->method('get')->willReturn($storage[$key] ?? null);
                $item->method('set')->willReturnCallback(function ($value) use ($key, &$storage, $item) {
                    $storage[$key] = $value;

                    return $item;
                });
                $item->method('expiresAfter')->willReturnSelf();

                return $item;
            });

        $pool->method('save')->willReturn(true);

        $cache = new SchemaCache($pool);
        $doc = $this->createDocument();

        $cache->set('stored_key', $doc);

        self::assertNull($cache->get('missing_key'));
        self::assertSame($doc, $cache->get('stored_key'));
    }

    /**
     * CA-02: Setting the same key twice replaces the previously stored
     * document — the second document becomes the one retrieved.
     */
    #[Test]
    public function overwrite_same_key_replaces_previous_document(): void
    {
        $storage = [];

        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$storage) {
                $item = $this->createStub(CacheItemInterface::class);
                $item->method('isHit')->willReturn(array_key_exists($key, $storage));
                $item->method('get')->willReturn($storage[$key] ?? null);
                $item->method('set')->willReturnCallback(function ($value) use ($key, &$storage, $item) {
                    $storage[$key] = $value;

                    return $item;
                });
                $item->method('expiresAfter')->willReturnSelf();

                return $item;
            });

        $pool->method('save')->willReturn(true);

        $cache = new SchemaCache($pool);
        $docV1 = $this->createDocumentWithTitle('First revision');
        $docV2 = $this->createDocumentWithTitle('Second revision');

        $cache->set('spec', $docV1);
        self::assertSame($docV1, $cache->get('spec'));

        $cache->set('spec', $docV2);
        $retrieved = $cache->get('spec');

        self::assertSame($docV2, $retrieved);
        self::assertNotSame($docV1, $retrieved);
    }

    /**
     * CA-02: SchemaCache itself is a readonly class (immutable wrapper);
     * the underlying pool state changes, but no setter exposes mutations
     * on the SchemaCache instance. Verify readonly-by-construction.
     */
    #[Test]
    public function schema_cache_is_readonly_class(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new SchemaCache($pool, 3600);

        $reflection = new ReflectionClass($cache);

        self::assertTrue($reflection->isReadOnly());
    }

    /**
     * CA-02: Calling set on one SchemaCache instance does not leak into
     * another instance built on a different pool.
     */
    #[Test]
    public function set_on_one_cache_does_not_affect_other_cache_with_different_pool(): void
    {
        $storage1 = [];
        $storage2 = [];

        $pool1 = $this->buildInMemoryPool($storage1);
        $pool2 = $this->buildInMemoryPool($storage2);

        $cache1 = new SchemaCache($pool1);
        $cache2 = new SchemaCache($pool2);

        $doc1 = $this->createDocumentWithTitle('one');
        $doc2 = $this->createDocumentWithTitle('two');

        $cache1->set('shared_key', $doc1);
        $cache2->set('shared_key', $doc2);

        self::assertSame($doc1, $cache1->get('shared_key'));
        self::assertSame($doc2, $cache2->get('shared_key'));
    }

    /**
     * Build a PHPUnit mock that behaves like an in-memory cache pool backed
     * by the given reference array.
     *
     * @param array<string, mixed> $storage
     */
    private function buildInMemoryPool(array &$storage): CacheItemPoolInterface
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool
            ->method('getItem')
            ->willReturnCallback(function (string $key) use (&$storage) {
                $item = $this->createStub(CacheItemInterface::class);
                $item->method('isHit')->willReturn(array_key_exists($key, $storage));
                $item->method('get')->willReturn($storage[$key] ?? null);
                $item->method('set')->willReturnCallback(function ($value) use ($key, &$storage, $item) {
                    $storage[$key] = $value;

                    return $item;
                });
                $item->method('expiresAfter')->willReturnSelf();

                return $item;
            });

        $pool->method('save')->willReturn(true);
        $pool->method('hasItem')->willReturnCallback(fn(string $key): bool => array_key_exists($key, $storage));

        return $pool;
    }

    private function createMockCachePool(): CacheItemPoolInterface
    {
        return $this->createMock(CacheItemPoolInterface::class);
    }

    private function createDocument(): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(
                title: 'Test API',
                version: '1.0.0',
            ),
        );
    }

    private function createDocumentWithTitle(string $title): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(
                title: $title,
                version: '1.0.0',
            ),
        );
    }
}
