<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Cache;

use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class SchemaCacheTest extends TestCase
{
    #[Test]
    public function get_returns_cached_document(): void
    {
        $pool = $this->createMockCachePool();
        $document = $this->createDocument();

        $cacheItem = $this->createMock(CacheItemInterface::class);
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

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($this->createCacheItem(null, false));

        $cache = new SchemaCache($pool);
        $result = $cache->get('test_key');

        self::assertNull($result);
    }

    #[Test]
    public function get_returns_null_when_cached_value_is_not_document(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($this->createCacheItem('invalid_value', true));

        $cache = new SchemaCache($pool);
        $result = $cache->get('test_key');

        self::assertNull($result);
    }

    #[Test]
    public function set_saves_document_to_cache(): void
    {
        $pool = $this->createMockCachePool();
        $document = $this->createDocument();
        $cacheItem = $this->createCacheItem(null, false);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

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
            ->method('get')
            ->willReturn(null);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

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
            ->method('getItem')
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
            ->method('get')
            ->willReturn(null);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

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
            ->method('getItem')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new SchemaCache($pool);
        $cache->set('test_key', $document);
    }

    private function createMockCachePool(): CacheItemPoolInterface
    {
        return $this->createMock(CacheItemPoolInterface::class);
    }

    private function createCacheItem(mixed $value, bool $isHit): CacheItemInterface
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->method('get')
            ->willReturn($value);

        $item
            ->method('isHit')
            ->willReturn($isHit);

        $item
            ->method('set')
            ->willReturnSelf();

        $item
            ->method('expiresAfter')
            ->willReturnSelf();

        return $item;
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
}
