<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\CompilationCache;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use ReflectionClass;

use function array_key_exists;

final class CompilationCacheTest extends TestCase
{
    #[Test]
    public function get_returns_null_when_cache_miss(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(false);

        $pool
            ->expects($this->once())
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

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn('<?php return true;');

        $pool
            ->expects($this->once())
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

        $cacheItem = $this->createStub(CacheItemInterface::class);
        $cacheItem
            ->method('isHit')
            ->willReturn(true);
        $cacheItem
            ->method('get')
            ->willReturn(['not', 'a', 'string']);

        $pool
            ->expects($this->once())
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

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with('<?php return true;')
            ->willReturnSelf();
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->willReturnSelf();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new CompilationCache($pool);

        $cache->set('test_hash', '<?php return true;');
    }

    #[Test]
    public function generateKey_creates_unique_hash_for_schema(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
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
        $pool = $this->createStub(CacheItemPoolInterface::class);
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
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $key = $cache->generateKey($schema);

        self::assertStringContainsString('validator_compilation.', $key);
    }

    #[Test]
    public function generateKey_with_custom_namespace(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool, 'custom_namespace');

        $schema = new Schema(type: 'string');

        $key = $cache->generateKey($schema);

        self::assertStringContainsString('custom_namespace.', $key);
    }

    #[Test]
    public function generateKey_hashes_all_schema_properties(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
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
        $pool = $this->createStub(CacheItemPoolInterface::class);
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
        $pool = $this->createStub(CacheItemPoolInterface::class);
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
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', minLength: null);
        $schema2 = new Schema(type: 'string');

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_format(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', format: 'email');
        $schema2 = new Schema(type: 'string', format: 'uri');

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_title(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', title: 'Schema A');
        $schema2 = new Schema(type: 'string', title: 'Schema B');

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_description(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', description: 'First description');
        $schema2 = new Schema(type: 'string', description: 'Second description');

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_deprecated(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', deprecated: false);
        $schema2 = new Schema(type: 'string', deprecated: true);

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_nullable(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', nullable: false);
        $schema2 = new Schema(type: 'string', nullable: true);

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_default(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', default: 'alpha', hasDefault: true);
        $schema2 = new Schema(type: 'string', default: 'beta', hasDefault: true);

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_const(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', const: 'fixed-a', hasConst: true);
        $schema2 = new Schema(type: 'string', const: 'fixed-b', hasConst: true);

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_discriminator(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
        );
        $schema2 = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'kind'),
        );

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_readOnly_writeOnly(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', readOnly: false, writeOnly: false);
        $schema2 = new Schema(type: 'string', readOnly: true, writeOnly: true);

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    /**
     * CP-06: CompilationCache uses a hardcoded TTL of 86400 seconds (24h).
     * Verify that set() passes this exact value to expiresAfter().
     */
    #[Test]
    public function set_uses_hardcoded_ttl_of_86400_seconds(): void
    {
        $pool = $this->createMock(CacheItemPoolInterface::class);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem
            ->expects($this->once())
            ->method('set')
            ->with('<?php return true;')
            ->willReturnSelf();
        $cacheItem
            ->expects($this->once())
            ->method('expiresAfter')
            ->with(86400)
            ->willReturnSelf();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new CompilationCache($pool);

        $cache->set('test_hash', '<?php return true;');
    }

    /**
     * CP-06: The TTL is exactly 86400 (24 hours), matching the constant
     * DEFAULT_CACHE_TTL. This locks the value against accidental changes.
     */
    #[Test]
    public function ttl_value_is_documented_as_86400_seconds(): void
    {
        $reflection = new ReflectionClass(CompilationCache::class);
        $constants = $reflection->getConstants();

        self::assertArrayHasKey('DEFAULT_CACHE_TTL', $constants);
        self::assertSame(86400, $constants['DEFAULT_CACHE_TTL']);
    }

    /**
     * CP-06 (end-to-end): After set() the same compiled code must be
     * retrievable via get() on the next call. Verifies the round-trip.
     */
    #[Test]
    public function round_trip_set_then_get_returns_stored_code(): void
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

        $cache = new CompilationCache($pool);
        $code = '<?php readonly class Foo { public function validate(mixed $data): void {} }';

        $cache->set('foo_hash', $code);

        $retrieved = $cache->get('foo_hash');

        self::assertSame($code, $retrieved);
    }

    /**
     * CP-06: compileWithCache() round-trip — first call compiles and stores,
     * second call returns the cached value unchanged.
     */
    #[Test]
    public function compile_with_cache_round_trip_returns_cached_code_on_second_call(): void
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

        $cache = new CompilationCache($pool);
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $first = $compiler->compileWithCache($schema, 'CachedStringValidator', $cache);
        $second = $compiler->compileWithCache($schema, 'CachedStringValidator', $cache);

        self::assertSame($first, $second);
        self::assertStringContainsString('is_string($data)', $first);
    }
}
