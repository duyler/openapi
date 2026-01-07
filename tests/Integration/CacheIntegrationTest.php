<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CacheIntegrationTest extends TestCase
{
    #[Test]
    public function cache_hit_on_second_load(): void
    {
        $isHitFirstCall = false;
        $isHitSecondCall = true;

        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItemMiss = $this->createCacheItem();
        $cacheItemMiss
            ->method('isHit')
            ->willReturn($isHitFirstCall);

        $cacheItemHit = $this->createCacheItem();
        $cacheItemHit
            ->method('isHit')
            ->willReturn($isHitSecondCall);

        $getItemCallCount = 0;
        $pool
            ->method('getItem')
            ->willReturnCallback(function () use (&$getItemCallCount, $cacheItemMiss, $cacheItemHit) {
                $getItemCallCount++;
                if (1 === $getItemCallCount) {
                    return $cacheItemMiss;
                }
                return $cacheItemHit;
            });

        $cache = new SchemaCache($pool);

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withCache($cache);

        $validator1 = $builder->build();
        self::assertNotNull($validator1->document);

        $validator2 = $builder->build();
        self::assertNotNull($validator2->document);
    }

    #[Test]
    public function cache_miss_on_first_load(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createCacheItem();
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool);

        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withCache($cache);

        $validator = $builder->build();

        self::assertNotNull($validator->document);
    }

    #[Test]
    public function cache_with_custom_ttl(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createCacheItem();
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new SchemaCache($pool, 7200);

        self::assertInstanceOf(SchemaCache::class, $cache);
    }

    #[Test]
    public function cache_delete_works(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $pool
            ->method('deleteItem')
            ->willReturn(true);

        $cache = new SchemaCache($pool);

        $cache->delete('test_key');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function cache_clear_works(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $pool
            ->method('clear')
            ->willReturn(true);

        $cache = new SchemaCache($pool);

        $cache->clear();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function cache_has_works(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $pool
            ->method('hasItem')
            ->willReturn(true);

        $cache = new SchemaCache($pool);

        self::assertTrue($cache->has('test_key'));
    }

    private function createCacheItem(): CacheItemInterface
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item
            ->method('set')
            ->willReturnSelf();
        $item
            ->method('expiresAfter')
            ->willReturnSelf();
        return $item;
    }
}
