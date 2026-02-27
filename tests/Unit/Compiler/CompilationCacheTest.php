<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\CompilationCache;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CompilationCacheTest extends TestCase
{
    #[Test]
    public function get_returns_null_when_cache_miss(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createCacheItem();
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new CompilationCache($pool);

        $result = $cache->get('test_hash');

        self::assertNull($result);
    }

    #[Test]
    public function get_returns_code_when_cache_hit(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createCacheItem();
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn('<?php return true;');

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new CompilationCache($pool);

        $result = $cache->get('test_hash');

        self::assertSame('<?php return true;', $result);
    }

    #[Test]
    public function get_returns_null_when_cache_hit_but_not_string(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createCacheItem();
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn(['not', 'a', 'string']);

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);

        $cache = new CompilationCache($pool);

        $result = $cache->get('test_hash');

        self::assertNull($result);
    }

    #[Test]
    public function set_stores_compiled_code(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createCacheItem();
        $cacheItem
            ->method('set')
            ->willReturnSelf();
        $cacheItem
            ->method('expiresAfter')
            ->willReturnSelf();

        $pool
            ->method('getItem')
            ->willReturn($cacheItem);
        $pool
            ->method('save')
            ->willReturn(true);

        $cache = new CompilationCache($pool);

        $cache->set('test_hash', '<?php return true;');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function generateKey_creates_unique_hash_for_schema(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(
            type: 'string',
            minLength: 1,
            maxLength: 100,
        );

        $schema2 = new Schema(
            type: 'string',
            minLength: 1,
            maxLength: 100,
        );

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertSame($key1, $key2);
    }

    #[Test]
    public function generateKey_creates_different_hash_for_different_schemas(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(
            type: 'string',
            minLength: 1,
        );

        $schema2 = new Schema(
            type: 'string',
            minLength: 10,
        );

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_includes_namespace(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $key = $cache->generateKey($schema);

        self::assertStringContainsString('validator_compilation.', $key);
    }

    #[Test]
    public function generateKey_with_custom_namespace(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool, 'custom_namespace');

        $schema = new Schema(type: 'string');

        $key = $cache->generateKey($schema);

        self::assertStringContainsString('custom_namespace.', $key);
    }

    #[Test]
    public function generateKey_hashes_all_schema_properties(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(
            type: 'string',
            minLength: 1,
            maxLength: 100,
            pattern: '^[a-z]+$',
            enum: ['a', 'b'],
        );

        $schema2 = new Schema(
            type: 'string',
            minLength: 1,
            maxLength: 100,
            pattern: '^[a-z]+$',
            enum: ['a', 'b'],
        );

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertSame($key1, $key2);
    }

    #[Test]
    public function generateKey_different_for_nested_schemas(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $schema2 = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'integer'),
            ],
        );

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_different_for_array_schemas(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        );

        $schema2 = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_handles_null_properties(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', minLength: null);
        $schema2 = new Schema(type: 'string');

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertSame($key1, $key2);
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
