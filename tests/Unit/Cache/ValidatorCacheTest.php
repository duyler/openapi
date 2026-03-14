<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Cache;

use Duyler\OpenApi\Cache\ValidatorCache;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class ValidatorCacheTest extends TestCase
{
    #[Test]
    public function get_returns_cached_schema(): void
    {
        $pool = $this->createMockCachePool();
        $schema = $this->createSchema();

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn($schema);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $cache = new ValidatorCache($pool);
        $result = $cache->get('test_key');

        self::assertSame($schema, $result);
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

        $cache = new ValidatorCache($pool);
        $result = $cache->get('test_key');

        self::assertNull($result);
    }

    #[Test]
    public function get_returns_null_when_cached_value_is_not_schema(): void
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

        $cache = new ValidatorCache($pool);
        $result = $cache->get('test_key');

        self::assertNull($result);
    }

    #[Test]
    public function set_saves_schema_to_cache(): void
    {
        $pool = $this->createMockCachePool();
        $schema = $this->createSchema();

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with($schema)
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

        $cache = new ValidatorCache($pool, 3600);
        $cache->set('test_key', $schema);
    }

    #[Test]
    public function delete_removes_schema_from_cache(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('deleteItem')
            ->with('test_key');

        $cache = new ValidatorCache($pool);
        $cache->delete('test_key');
    }

    #[Test]
    public function clear_clears_all_cache(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('clear');

        $cache = new ValidatorCache($pool);
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

        $cache = new ValidatorCache($pool);
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

        $cache = new ValidatorCache($pool);
        $result = $cache->has('test_key');

        self::assertFalse($result);
    }

    private function createMockCachePool(): CacheItemPoolInterface
    {
        return $this->createMock(CacheItemPoolInterface::class);
    }

    private function createSchema(): Schema
    {
        return new Schema(type: 'string');
    }
}
