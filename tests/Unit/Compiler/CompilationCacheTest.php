<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\CompilationCache;
use Duyler\OpenApi\Compiler\Exception\CompilationCacheException;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;

use function array_key_exists;
use function strlen;

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
     * EI-064: CompilationCache uses a default TTL of 86400 seconds (24h).
     * Verify that set() passes this value to expiresAfter() by default.
     */
    #[Test]
    public function set_uses_default_ttl_of_86400_seconds(): void
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
            ->with(CompilationCache::DEFAULT_TTL)
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
     * EI-064: Custom TTL passed to the constructor is used by set().
     */
    #[Test]
    public function set_uses_custom_ttl_from_constructor(): void
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
            ->with(3600)
            ->willReturnSelf();

        $pool
            ->expects($this->once())
            ->method('getItem')
            ->willReturn($cacheItem);
        $pool
            ->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $cache = new CompilationCache($pool, ttl: 3600);

        $cache->set('test_hash', '<?php return true;');
    }

    /**
     * EI-064: The default TTL is publicly exposed as DEFAULT_TTL.
     */
    #[Test]
    public function default_ttl_constant_is_public_and_equals_86400(): void
    {
        $reflection = new ReflectionClass(CompilationCache::class);
        $constants = $reflection->getConstants();

        self::assertArrayHasKey('DEFAULT_TTL', $constants);
        self::assertSame(86400, $constants['DEFAULT_TTL']);
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

    /**
     * EI-046: self-referential schema must not cause a stack overflow. The
     * generated key must be a stable 64-character SHA-256 hash, and repeated
     * calls for the same object must return the same value.
     */
    #[Test]
    public function generateKey_handles_self_referential_schema(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = $this->createSelfReferentialSchema();

        $key1 = $cache->generateKey($schema);

        $freshCache = new CompilationCache($pool);
        $key2 = $freshCache->generateKey($schema);

        $hashPart = substr($key1, strlen('validator_compilation.'));

        self::assertSame(64, strlen($hashPart));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hashPart);
        self::assertSame($key1, $key2);
    }

    /**
     * EI-046: json_encode failures must be wrapped in a domain exception with
     * the original JsonException available via getPrevious().
     */
    #[Test]
    public function generateKey_wraps_json_exception_in_compilation_cache_exception(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(
            type: 'string',
            default: [fopen('php://memory', 'r')],
            hasDefault: true,
        );

        $caught = null;

        try {
            $cache->generateKey($schema);
        } catch (CompilationCacheException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Failed to encode schema for hash:', $caught->getMessage());

        $previous = $caught->getPrevious();

        self::assertInstanceOf(JsonException::class, $previous);
    }

    /**
     * Documents a known limitation: cross-instance hash stability for cyclic
     * schemas is NOT guaranteed because spl_object_id() is embedded in the
     * __circular_ref__ marker. This test asserts the CURRENT behavior so that
     * any future change (e.g., switching to path-based markers) explicitly
     * breaks this test and forces a contract update.
     *
     * The same-instance stability is covered by generateKey_handles_self_referential_schema.
     */
    #[Test]
    public function generateKey_cyclic_schema_different_instances_yields_different_hash(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = $this->createSelfReferentialSchema();
        $schema2 = $this->createSelfReferentialSchema();

        $key1 = $cache->generateKey($schema1);
        $key2 = $cache->generateKey($schema2);

        self::assertNotSame($key1, $key2);
    }

    private function createSelfReferentialSchema(): Schema
    {
        $schema = new Schema(type: 'object');
        $schema = $schema->withOverrides(properties: ['self' => &$schema]);

        return $schema;
    }
}
