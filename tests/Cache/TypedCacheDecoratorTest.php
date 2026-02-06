<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Cache;

use Duyler\OpenApi\Cache\TypedCacheDecorator;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

final class TypedCacheDecoratorTest extends TestCase
{
    #[Test]
    public function get_returns_cached_value_of_expected_type(): void
    {
        $pool = $this->createMockCachePool();
        $schema = $this->createSchema();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($this->createCacheItem($schema, true));

        $decorator = new TypedCacheDecorator($pool);
        $result = $decorator->get('test_key', Schema::class);

        self::assertSame($schema, $result);
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

        $decorator = new TypedCacheDecorator($pool);
        $result = $decorator->get('test_key', Schema::class);

        self::assertNull($result);
    }

    #[Test]
    public function get_returns_null_when_cached_value_is_null(): void
    {
        $pool = $this->createMockCachePool();

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn(null);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $decorator = new TypedCacheDecorator($pool);
        $result = $decorator->get('test_key', Schema::class);

        self::assertNull($result);
    }

    #[Test]
    public function get_returns_null_when_cached_value_is_not_of_expected_type(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($this->createCacheItem('invalid_value', true));

        $decorator = new TypedCacheDecorator($pool);
        $result = $decorator->get('test_key', Schema::class);

        self::assertNull($result);
    }

    #[Test]
    public function get_throws_exception_when_expected_type_does_not_exist(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($this->createCacheItem('value', true));

        $decorator = new TypedCacheDecorator($pool);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected type class does not exist: NonExistentClass');

        $decorator->get('test_key', 'NonExistentClass');
    }

    #[Test]
    public function set_saves_value_to_cache(): void
    {
        $pool = $this->createMockCachePool();
        $schema = $this->createSchema();
        $cacheItem = $this->createCacheItem(null, false);

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->with('test_key')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $decorator = new TypedCacheDecorator($pool, 3600);
        $decorator->set('test_key', $schema);
    }

    #[Test]
    public function set_uses_custom_ttl_when_provided(): void
    {
        $pool = $this->createMockCachePool();
        $schema = $this->createSchema();
        $cacheItem = $this->createCacheItem(null, false);

        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(7200);

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $decorator = new TypedCacheDecorator($pool, 7200);
        $decorator->set('test_key', $schema);
    }

    #[Test]
    public function delete_removes_value_from_cache(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('deleteItem')
            ->with('test_key');

        $decorator = new TypedCacheDecorator($pool);
        $decorator->delete('test_key');
    }

    #[Test]
    public function clear_clears_all_cache(): void
    {
        $pool = $this->createMockCachePool();

        $pool
            ->expects($this->once())
            ->method('clear');

        $decorator = new TypedCacheDecorator($pool);
        $decorator->clear();
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

        $decorator = new TypedCacheDecorator($pool);
        $result = $decorator->has('test_key');

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

        $decorator = new TypedCacheDecorator($pool);
        $result = $decorator->has('test_key');

        self::assertFalse($result);
    }

    #[Test]
    public function set_uses_default_ttl_when_not_provided(): void
    {
        $pool = $this->createMockCachePool();
        $schema = $this->createSchema();
        $cacheItem = $this->createCacheItem(null, false);

        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(3600);

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $decorator = new TypedCacheDecorator($pool);
        $decorator->set('test_key', $schema);
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

    private function createSchema(): Schema
    {
        return new Schema(type: 'string');
    }
}
