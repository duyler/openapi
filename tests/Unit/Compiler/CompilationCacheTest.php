<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\CompilationCache;
use Duyler\OpenApi\Compiler\Exception\CompilationCacheException;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use InvalidArgumentException;
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

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

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

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_includes_namespace(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $key = $cache->generateKey($schema, 'DefaultValidator');

        self::assertStringContainsString('validator_compilation.', $key);
    }

    #[Test]
    public function generateKey_with_custom_namespace(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool, 'custom_namespace');

        $schema = new Schema(type: 'string');

        $key = $cache->generateKey($schema, 'DefaultValidator');

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

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

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

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

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

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_handles_null_properties(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', minLength: null);
        $schema2 = new Schema(type: 'string');

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_format(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', format: 'email');
        $schema2 = new Schema(type: 'string', format: 'uri');

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_title(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', title: 'Schema A');
        $schema2 = new Schema(type: 'string', title: 'Schema B');

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_description(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', description: 'First description');
        $schema2 = new Schema(type: 'string', description: 'Second description');

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_deprecated(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', deprecated: false);
        $schema2 = new Schema(type: 'string', deprecated: true);

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_nullable(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', nullable: false);
        $schema2 = new Schema(type: 'string', nullable: true);

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_default(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', default: 'alpha', hasDefault: true);
        $schema2 = new Schema(type: 'string', default: 'beta', hasDefault: true);

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_const(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', const: 'fixed-a', hasConst: true);
        $schema2 = new Schema(type: 'string', const: 'fixed-b', hasConst: true);

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

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

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertNotSame($key1, $key2);
    }

    #[Test]
    public function generateKey_no_collision_for_previously_excluded_readOnly_writeOnly(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = new Schema(type: 'string', readOnly: false, writeOnly: false);
        $schema2 = new Schema(type: 'string', readOnly: true, writeOnly: true);

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

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
     * Constructor guard: a non-positive TTL is rejected at construction
     * time rather than silently producing a cache that expires
     * immediately. The error message must include the offending value so
     * operators can debug misconfigurations.
     */
    #[Test]
    public function constructor_rejects_non_positive_ttl(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL must be a positive integer, got 0.');

        new CompilationCache($pool, ttl: 0);
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

        $key1 = $cache->generateKey($schema, 'DefaultValidator');

        $freshCache = new CompilationCache($pool);
        $key2 = $freshCache->generateKey($schema, 'DefaultValidator');

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
            $cache->generateKey($schema, 'DefaultValidator');
        } catch (CompilationCacheException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Failed to encode schema for hash:', $caught->getMessage());

        $previous = $caught->getPrevious();

        self::assertInstanceOf(JsonException::class, $previous);
    }

    /**
     * Symmetry guard: `documentFingerprint` runs the same `json_encode`
     * path over a snapshot of `components.schemas`. A non-JSON-encodable
     * value (resource) inside a component schema MUST be wrapped in a
     * `CompilationCacheException` with the original `JsonException`
     * preserved on `getPrevious()`, mirroring the wrapper used in
     * `calculateSchemaHash`. Without the wrapper, the raw
     * `JsonException` would leak through the public `generateKey`
     * surface and break the domain-exception contract.
     */
    #[Test]
    public function document_fingerprint_wraps_json_exception_in_compilation_cache_exception(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Broken' => new Schema(
                        type: 'string',
                        default: [fopen('php://memory', 'r')],
                        hasDefault: true,
                    ),
                ],
            ),
        );

        $caught = null;

        try {
            $cache->generateKey($schema, 'DefaultValidator', $document);
        } catch (CompilationCacheException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Failed to encode document components for fingerprint:', $caught->getMessage());

        $previous = $caught->getPrevious();

        self::assertInstanceOf(JsonException::class, $previous);
    }

    /**
     * Cross-instance hash stability for cyclic schemas: WeakMap cycle marker
     * uses a deterministic per-call counter instead of spl_object_id(), so two
     * structurally identical self-referential schemas MUST produce the same
     * hash. Regression guard for P-027 (cache-poisoning via spl_object_id
     * recycling after GC).
     */
    #[Test]
    public function generateKey_cyclic_schema_different_instances_yields_same_hash(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = $this->createSelfReferentialSchema();
        $schema2 = $this->createSelfReferentialSchema();

        $key1 = $cache->generateKey($schema1, 'DefaultValidator');
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertSame($key1, $key2);
    }

    /**
     * Forced GC between two generateKey() calls MUST NOT change the hash.
     * With spl_object_id() recycling, a new schema allocated after GC could
     * reuse a previously freed id, corrupting the cycle marker. The WeakMap
     * per-call instance is GC-aware (entries vanish only when the key object
     * is unreachable, which cannot happen mid-call).
     */
    #[Test]
    public function generateKey_cyclic_schema_survives_gc_between_calls(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema1 = $this->createSelfReferentialSchema();
        $key1 = $cache->generateKey($schema1, 'DefaultValidator');

        for ($i = 0; $i < 20; ++$i) {
            $scratch = new Schema(type: 'object', properties: ['p' . $i => new Schema(type: 'string')]);
            unset($scratch);
        }

        gc_collect_cycles();

        $schema2 = $this->createSelfReferentialSchema();
        $key2 = $cache->generateKey($schema2, 'DefaultValidator');

        self::assertSame($key1, $key2);
    }

    /**
     * R3-ARCH-001: the cache key MUST differ when the same schema is
     * compiled under different class names. Without className in the
     * hash input, the second compile would reuse a stale cached class
     * with the wrong short name (`Fatal error: Class "AdminValidator"
     * not found` after `require_once` of the cached file).
     */
    #[Test]
    public function generate_key_differs_for_different_class_names(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $key1 = $cache->generateKey($schema, 'UserValidator');
        $key2 = $cache->generateKey($schema, 'AdminValidator');

        self::assertNotSame($key1, $key2);
    }

    /**
     * Determinism guard: same Schema instance, same class name, two calls
     * (the second served from the in-memory compound-key WeakMap cache)
     * MUST return byte-identical keys.
     */
    #[Test]
    public function generate_key_same_for_same_class_name_and_schema(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $key1 = $cache->generateKey($schema, 'UserValidator');
        $key2 = $cache->generateKey($schema, 'UserValidator');

        self::assertSame($key1, $key2);
    }

    /**
     * R3-SEC-004: a schema with `$ref: '#/components/schemas/Base'` MUST
     * hash differently when `Base` resolves to a different target schema.
     * Without document-context in the hash input, document v1 (Base is
     * integer) and document v2 (Base is string) would collide and the
     * second compile would silently reuse v1's stale validator.
     */
    #[Test]
    public function generate_key_differs_for_different_document_context_with_ref(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/Base');

        $documentV1 = $this->buildDocumentWithBase(new Schema(type: 'integer'));
        $documentV2 = $this->buildDocumentWithBase(new Schema(type: 'string'));

        $key1 = $cache->generateKey($schema, 'BaseValidator', $documentV1);
        $key2 = $cache->generateKey($schema, 'BaseValidator', $documentV2);

        self::assertNotSame($key1, $key2);
    }

    /**
     * Determinism guard for document-context hashing: the same Schema and
     * the same document MUST produce identical keys across calls. The
     * document fingerprint is memoized via WeakMap, but that is an
     * optimisation — the output must be byte-identical regardless.
     */
    #[Test]
    public function generate_key_same_for_same_document_context_with_ref(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/Base');
        $document = $this->buildDocumentWithBase(new Schema(type: 'integer'));

        $key1 = $cache->generateKey($schema, 'BaseValidator', $document);
        $key2 = $cache->generateKey($schema, 'BaseValidator', $document);

        self::assertSame($key1, $key2);
    }

    /**
     * Tenant isolation: two documents whose `Base` schema resolves to
     * identical content MUST still produce different cache keys when the
     * documents differ in any other component. Without the document
     * fingerprint in the hash input, tenants sharing a PSR-6 pool would
     * cross-share validator code (cache-poisoning vector when one
     * tenant is malicious and controls the className of a poisoned
     * cached class). The fingerprint is the only input that
     * differentiates these cases because schema resolution alone
     * produces identical resolved snapshots.
     */
    #[Test]
    public function generate_key_differs_across_documents_with_identical_resolved_schema(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/Base');

        $documentV1 = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Tenant A', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Base' => new Schema(type: 'string'),
                    'TenantAOnly' => new Schema(type: 'integer'),
                ],
            ),
        );

        $documentV2 = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Tenant B', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Base' => new Schema(type: 'string'),
                    'TenantBOnly' => new Schema(type: 'boolean'),
                ],
            ),
        );

        $key1 = $cache->generateKey($schema, 'BaseValidator', $documentV1);
        $key2 = $cache->generateKey($schema, 'BaseValidator', $documentV2);

        self::assertNotSame($key1, $key2);
    }

    /**
     * R3-SEC-004 fail-closed: a schema containing a `$ref` MUST throw
     * `CompilationCacheException` when no document is provided. Previously
     * the literal `$ref` string was hashed as-is, silently colliding across
     * documents that resolved the same pointer to different targets.
     */
    #[Test]
    public function generate_key_throws_when_schema_has_ref_and_document_is_null(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/Base');

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Schema contains $ref but no document context provided');

        $cache->generateKey($schema, 'BaseValidator');
    }

    /**
     * R3-SEC-004 fail-closed for nested `$ref`: a top-level `$ref`-free
     * object schema whose property carries a `$ref` MUST also throw
     * `CompilationCacheException` when no document is provided. The
     * top-level-only check is insufficient because
     * `SchemaToArrayConverter::toSnapshotArray` serialises nested `$ref`
     * pointers as opaque strings, so two documents with different
     * target content for the same nested pointer would collide.
     */
    #[Test]
    public function generate_key_throws_when_nested_ref_in_properties_and_document_is_null(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(
            type: 'object',
            properties: ['child' => new Schema(ref: '#/components/schemas/Base')],
        );

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Schema contains $ref but no document context provided');

        $cache->generateKey($schema, 'NestedPropertyValidator');
    }

    /**
     * R3-SEC-004 fail-closed for nested `$ref` in array items: same
     * invariant as the properties case but for `type: array` schemas
     * whose `items` is a `$ref` pointer.
     */
    #[Test]
    public function generate_key_throws_when_nested_ref_in_items_and_document_is_null(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Base'),
        );

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Schema contains $ref but no document context provided');

        $cache->generateKey($schema, 'NestedItemsValidator');
    }

    /**
     * R3-SEC-004 fail-closed recursion: a 3-level deep schema tree
     * (`a -> properties.b -> properties.c -> $ref`) MUST also throw,
     * proving that `schemaContainsRef` recurses through nested
     * properties rather than stopping at the first level.
     */
    #[Test]
    public function generate_key_throws_when_deeply_nested_ref_and_document_is_null(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $deepest = new Schema(
            type: 'object',
            properties: ['c' => new Schema(ref: '#/components/schemas/Base')],
        );
        $middle = new Schema(
            type: 'object',
            properties: ['b' => $deepest],
        );
        $schema = new Schema(
            type: 'object',
            properties: ['a' => $middle],
        );

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Schema contains $ref but no document context provided');

        $cache->generateKey($schema, 'DeepNestedValidator');
    }

    /**
     * BC path: schemas without `$ref` continue to work without a document
     * argument. This is the legacy `compileWithCache(schema, name, cache)`
     * flow that must remain valid for any schema that does not transitively
     * contain a `$ref`.
     */
    #[Test]
    public function generate_key_works_for_schema_without_ref_and_null_document(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string', minLength: 1);

        $key = $cache->generateKey($schema, 'StringValidator');

        self::assertStringContainsString('validator_compilation.', $key);
    }

    /**
     * PSR-6 pool keys have implementation-specific length limits (Symfony
     * FilesystemAdapter caps around 50 chars + namespace prefix, other
     * adapters reject keys > 64 chars). The final SHA-256 collapse keeps
     * the key inside `namespace.length + 1 + 64` regardless of how long
     * the className or how nested the document is.
     */
    #[Test]
    public function generate_key_returns_compact_key_within_psr6_length(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');

        $longClassName = 'App\\Modules\\Whatever\\VeryLong\\Namespace\\Path\\To\\A\\ValidatorClass';
        $key = $cache->generateKey($schema, $longClassName);

        self::assertLessThanOrEqual(128, strlen($key));
    }

    /**
     * Cycle detection in `resolveRefsForHash`: a document where A points to
     * B and B points back to A MUST throw `CompilationCacheException` with
     * a message containing `Circular $ref`, instead of infinite-looping
     * during cache key computation.
     */
    #[Test]
    public function generate_key_detects_circular_ref(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/A');
        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'A' => new Schema(ref: '#/components/schemas/B'),
                    'B' => new Schema(ref: '#/components/schemas/A'),
                ],
            ),
        );

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Circular $ref detected while calculating cache key');

        $cache->generateKey($schema, 'CycleValidator', $document);
    }

    /**
     * End-to-end check that `compileWithCache` actually flows the new
     * `$className` and `$document` arguments into distinct cache keys: two
     * compiles of the same schema under different class names MUST emit
     * different generated code (the class short name appears in the
     * emitted PHP source).
     */
    #[Test]
    public function compile_with_cache_passes_class_name_and_document(): void
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

        $document = $this->buildDocumentWithBase(new Schema(type: 'string'));
        $schema = new Schema(ref: '#/components/schemas/Base');

        $code1 = $compiler->compileWithCache($schema, 'FirstRefValidator', $cache, $document);
        $code2 = $compiler->compileWithCache($schema, 'SecondRefValidator', $cache, $document);

        self::assertStringContainsString('class FirstRefValidator', $code1);
        self::assertStringContainsString('class SecondRefValidator', $code2);
    }

    /**
     * Memoization sanity: calling `generateKey` twice on the same Schema
     * object under the same (className, document) triple returns from the
     * in-memory WeakMap on the second call without re-hashing. Observable
     * effect: the second call is significantly faster, but more importantly
     * the result is byte-identical.
     */
    #[Test]
    public function generate_key_uses_compound_cache_on_repeated_call(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(type: 'string');
        $document = $this->buildDocumentWithBase(new Schema(type: 'integer'));

        $key1 = $cache->generateKey($schema, 'MemoValidator', $document);
        $key2 = $cache->generateKey($schema, 'MemoValidator', $document);

        self::assertSame($key1, $key2);
    }

    /**
     * Compound-cache dimension check: the same Schema under two different
     * documents MUST return different keys, proving that the compound-key
     * `WeakMap<Schema, array<string, string>>` correctly distinguishes
     * document contexts on the same schema object.
     */
    #[Test]
    public function generate_key_distinguishes_documents_for_same_schema_instance(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/Base');
        $documentV1 = $this->buildDocumentWithBase(new Schema(type: 'integer'));
        $documentV2 = $this->buildDocumentWithBase(new Schema(type: 'string'));

        $key1 = $cache->generateKey($schema, 'BaseValidator', $documentV1);
        $key2 = $cache->generateKey($schema, 'BaseValidator', $documentV2);

        self::assertNotSame($key1, $key2);
    }

    /**
     * Rejects `$ref` pointers that are not in `#/components/schemas/...`
     * form. The compiler does not support external or alternate-shape
     * refs at compile time, so the cache key generator must fail-closed.
     */
    #[Test]
    public function generate_key_rejects_unsupported_ref_shape(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: 'https://example.com/external.yaml#/Base');
        $document = $this->buildDocumentWithBase(new Schema(type: 'string'));

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Unsupported $ref for cache key');

        $cache->generateKey($schema, 'ExternalRefValidator', $document);
    }

    /**
     * Rejects `$ref` whose target is not present in the document's
     * `components.schemas` map. Without this guard, an unresolvable ref
     * would silently fall back to hashing the literal pointer string.
     */
    #[Test]
    public function generate_key_rejects_missing_component_target(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(ref: '#/components/schemas/Missing');
        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(schemas: ['Other' => new Schema(type: 'string')]),
        );

        $this->expectException(CompilationCacheException::class);
        $this->expectExceptionMessage('Schema not found: Missing');

        $cache->generateKey($schema, 'MissingTargetValidator', $document);
    }

    /**
     * Resolution recursion into `properties`: an object schema whose
     * property is a `$ref` MUST have the property resolved against the
     * document before hashing, so two documents that resolve the same
     * property `$ref` to different target schemas produce different keys.
     */
    #[Test]
    public function generate_key_resolves_nested_ref_in_properties(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(
            type: 'object',
            properties: ['child' => new Schema(ref: '#/components/schemas/Base')],
        );

        $documentV1 = $this->buildDocumentWithBase(new Schema(type: 'integer'));
        $documentV2 = $this->buildDocumentWithBase(new Schema(type: 'string'));

        $key1 = $cache->generateKey($schema, 'NestedPropertyValidator', $documentV1);
        $key2 = $cache->generateKey($schema, 'NestedPropertyValidator', $documentV2);

        self::assertNotSame($key1, $key2);
    }

    /**
     * Resolution recursion into `items`: an array schema whose `items`
     * is a `$ref` MUST have the items schema resolved against the
     * document before hashing, so two documents that resolve the same
     * items `$ref` to different target schemas produce different keys.
     */
    #[Test]
    public function generate_key_resolves_nested_ref_in_items(): void
    {
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $cache = new CompilationCache($pool);

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Base'),
        );

        $documentV1 = $this->buildDocumentWithBase(new Schema(type: 'integer'));
        $documentV2 = $this->buildDocumentWithBase(new Schema(type: 'string'));

        $key1 = $cache->generateKey($schema, 'NestedItemsValidator', $documentV1);
        $key2 = $cache->generateKey($schema, 'NestedItemsValidator', $documentV2);

        self::assertNotSame($key1, $key2);
    }

    private function createSelfReferentialSchema(): Schema
    {
        $schema = new Schema(type: 'object');
        $schema = $schema->withOverrides(properties: ['self' => &$schema]);

        return $schema;
    }

    private function buildDocumentWithBase(Schema $base): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: ['Base' => $base],
            ),
        );
    }
}
