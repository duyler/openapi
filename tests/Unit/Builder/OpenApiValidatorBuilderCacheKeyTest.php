<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use DateInterval;
use DateTimeInterface;
use ReflectionMethod;

use function array_key_exists;
use function clearstatcache;
use function count;
use function file_put_contents;
use function glob;
use function hash;
use function is_file;
use function sha1;
use function strlen;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

use function dirname;

use const PHP_EOL;

final class OpenApiValidatorBuilderCacheKeyTest extends TestCase
{
    private const string MINIMAL_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Cache Key Test
  version: 1.0.0
paths: {}
YAML;

    private const string FILE_KEY_PREFIX = 'openapi_spec_file_';
    private const string CONTENT_KEY_PREFIX = 'openapi_spec_content_';

    #[Test]
    public function string_cache_key_uses_sha256_length(): void
    {
        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build();

        $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());

        self::assertCount(1, $uniqueKeys);

        $key = $uniqueKeys[0];
        self::assertStringStartsWith(self::CONTENT_KEY_PREFIX, $key);

        $hash = substr($key, strlen(self::CONTENT_KEY_PREFIX));

        self::assertSame(64, strlen($hash), 'SHA-256 hex digest must be 64 chars, not 16 (xxh64)');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function string_cache_key_matches_sha256_of_content_hash_and_parse_config_fingerprint(): void
    {
        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build();

        $contentHash = hash('sha256', self::MINIMAL_YAML);
        $fingerprint = 'maxSpecDepth=100|maxSpecSizeBytes=1048576|externalRefAllowedRoot=|externalRefMaxBytes=10485760';
        $expected = self::CONTENT_KEY_PREFIX . hash('sha256', $contentHash . '|' . $fingerprint);

        $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());

        self::assertSame($expected, $uniqueKeys[0]);
    }

    #[Test]
    public function string_cache_key_is_not_xxh64_length(): void
    {
        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build();

        $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());
        $hash = substr($uniqueKeys[0], strlen(self::CONTENT_KEY_PREFIX));

        self::assertNotSame(16, strlen($hash), 'Cache key must not be xxh64 (16 hex chars)');
    }

    #[Test]
    public function file_cache_key_uses_sha256_length(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool = new KeyCapturingPool();
            $cache = new SchemaCache($pool);

            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache($cache)
                ->build();

            $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());
            $key = $uniqueKeys[0];
            $hash = substr($key, strlen(self::FILE_KEY_PREFIX));

            self::assertSame(64, strlen($hash));
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function file_cache_key_ignores_mtime_changes(): void
    {
        // After R3-SEC-003 the cache key is keyed by realpath + content hash.
        // mtime is intentionally NOT part of the key because it offered no
        // defence once an attacker controls write access to the spec file
        // (the `touch -r` workaround defeats metadata-only keys). The same
        // content must therefore yield the same key regardless of mtime.
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool1 = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool1))
                ->build();
            $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];

            touch($path, time() + 100);

            $pool2 = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool2))
                ->build();
            $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

            self::assertSame(
                $key1,
                $key2,
                'Cache key must ignore mtime after R3-SEC-003 content-hash fix (defends against touch -r cache-poisoning)',
            );
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function file_cache_key_changes_when_size_changes(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool1 = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool1))
                ->build();
            $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];

            $appended = self::MINIMAL_YAML . PHP_EOL . '# extra comment to change size' . PHP_EOL;
            $this->writeTempSpec($appended, $path);
            touch($path, time() + 200);

            $pool2 = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool2))
                ->build();
            $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

            self::assertNotSame($key1, $key2, 'Cache key must change when file content changes (content-hash fix, R3-SEC-003)');
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function file_cache_key_stable_across_rebuilds_with_unchanged_file(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool1 = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool1))
                ->build();
            $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];

            $pool2 = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool2))
                ->build();
            $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

            self::assertSame($key1, $key2, 'Same file content must produce identical cache key (content-hash fix, R3-SEC-003)');
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function file_cache_key_is_not_xxh64_length(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool = new KeyCapturingPool();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool))
                ->build();

            $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());
            $hash = substr($uniqueKeys[0], strlen(self::FILE_KEY_PREFIX));

            self::assertNotSame(16, strlen($hash), 'File cache key must not be xxh64');
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function cache_key_for_file_includes_content_hash(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $keyForContentA = $this->invokeGenerateCacheKeyFromFile($path, 'openapi: 3.2.0 a');
            $keyForContentB = $this->invokeGenerateCacheKeyFromFile($path, 'openapi: 3.2.0 b');

            self::assertNotSame(
                $keyForContentA,
                $keyForContentB,
                'Same path with different content must yield different cache keys (R3-SEC-003 content-hash fix)',
            );
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function cache_key_for_file_with_same_content_different_path_produces_different_keys(): void
    {
        $pathA = $this->writeTempSpec(self::MINIMAL_YAML, sys_get_temp_dir() . '/openapi_cache_key_test_a.yaml');
        $pathB = $this->writeTempSpec(self::MINIMAL_YAML, sys_get_temp_dir() . '/openapi_cache_key_test_b.yaml');

        try {
            $keyForPathA = $this->invokeGenerateCacheKeyFromFile($pathA, self::MINIMAL_YAML);
            $keyForPathB = $this->invokeGenerateCacheKeyFromFile($pathB, self::MINIMAL_YAML);

            self::assertNotSame(
                $keyForPathA,
                $keyForPathB,
                'Identical content under different paths must yield different keys (path-component is part of the hash input)',
            );
        } finally {
            $this->safeUnlink($pathA);
            $this->safeUnlink($pathB);
        }
    }

    #[Test]
    public function cache_key_for_file_excludes_mtime_and_size(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $keyBefore = $this->invokeGenerateCacheKeyFromFile($path, self::MINIMAL_YAML);

            touch($path, time() + 500);
            clearstatcache(true, $path);

            $keyAfter = $this->invokeGenerateCacheKeyFromFile($path, self::MINIMAL_YAML);

            self::assertSame(
                $keyBefore,
                $keyAfter,
                'Cache key must be invariant across mtime/size probes for identical path+content (mtime and size are not part of the hash input)',
            );
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function cache_key_for_file_falls_back_to_unresolved_path_when_realpath_fails(): void
    {
        $nonexistentPath = sys_get_temp_dir() . '/duyler-cache-key-missing-' . uniqid(more_entropy: true) . '.yaml';

        $key = $this->invokeGenerateCacheKeyFromFile($nonexistentPath, self::MINIMAL_YAML);

        self::assertStringStartsWith(self::FILE_KEY_PREFIX, $key);
        $hash = substr($key, strlen(self::FILE_KEY_PREFIX));
        self::assertSame(64, strlen($hash), 'Fallback key must still be a SHA-256 hex digest');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function cache_key_for_file_returns_prefixed_with_constant(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $key = $this->invokeGenerateCacheKeyFromFile($path, self::MINIMAL_YAML);

            self::assertStringStartsNotWith(self::CONTENT_KEY_PREFIX, $key);
            self::assertStringStartsWith(self::FILE_KEY_PREFIX, $key);
        } finally {
            $this->safeUnlink($path);
        }
    }

    /**
     * R4-SEC-008 anti-test (maxSpecDepth poisoning): caller A caches a
     * spec under maxSpecDepth=10 (depth=6 fits), caller B with the
     * stricter maxSpecDepth=5 must NOT receive the cached document —
     * instead the cache-miss triggers a re-parse that rejects the spec.
     * Without the parse-config fingerprint, caller B would silently
     * bypass the depth limit and read the cached document.
     */
    #[Test]
    public function max_spec_depth_change_prevents_cache_poisoning(): void
    {
        $spec = <<<'YAML'
openapi: 3.2.0
info:
  title: Depth Poisoning Test
  version: 1.0.0
paths: {}
x-l1:
  l2:
    l3:
      l4:
        l5:
          l6:
            l7: deep
YAML;

        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->withMaxSpecDepth(10)
            ->withCache($cache)
            ->build();

        $poisoningRejected = false;
        try {
            OpenApiValidatorBuilder::create()
                ->fromYamlString($spec)
                ->withMaxSpecDepth(5)
                ->withCache($cache)
                ->build();
        } catch (SpecTooLargeException) {
            $poisoningRejected = true;
        }

        self::assertTrue(
            $poisoningRejected,
            'Caller B with stricter maxSpecDepth=5 must NOT receive the cached document '
            . 'cached under maxSpecDepth=10. The fingerprint-based cache key must force a '
            . 're-parse that rejects the depth-6 spec (R4-SEC-008 cache-poisoning defence).',
        );
    }

    /**
     * R4-SEC-008 anti-test (externalRefAllowedRoot poisoning): two
     * callers that differ only in externalRefAllowedRoot must get
     * distinct cache keys. Without the fingerprint, caller B (root
     * `/etc`) would receive the document cached by caller A (root
     * `/safe`), bypassing its (presumably stricter) confinement
     * boundary.
     */
    #[Test]
    public function external_ref_allowed_root_change_produces_distinct_cache_keys(): void
    {
        $root1 = sys_get_temp_dir();
        $root2 = dirname(__DIR__, 3);

        $pool1 = new KeyCapturingPool();
        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withExternalRefAllowedRoot($root1)
            ->withCache(new SchemaCache($pool1))
            ->build();

        $pool2 = new KeyCapturingPool();
        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withExternalRefAllowedRoot($root2)
            ->withCache(new SchemaCache($pool2))
            ->build();

        $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];
        $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

        self::assertNotSame(
            $root1,
            $root2,
            'Test precondition: the two roots must be distinct paths.',
        );
        self::assertNotSame(
            $key1,
            $key2,
            'externalRefAllowedRoot is part of the parse-config fingerprint and must '
            . 'produce a distinct cache key (R4-SEC-008 confinement poisoning defence).',
        );
    }

    /**
     * Stability: two callers with identical parse-config and identical
     * spec content reuse the same cache key (cache-hit, single parse).
     * Guards against accidental over-invalidation of the cache.
     */
    #[Test]
    public function identical_parse_config_and_content_reuse_cache_key(): void
    {
        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withMaxSpecDepth(50)
            ->withCache($cache)
            ->build();

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withMaxSpecDepth(50)
            ->withCache($cache)
            ->build();

        $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());

        self::assertCount(
            1,
            $uniqueKeys,
            'Identical parse-config + content must produce a single cache key so the second '
            . 'build reuses the cached document instead of re-parsing.',
        );
    }

    /**
     * Unrelated-fields: runtime-validation toggles (coercion,
     * nullableAsType, strictFormats, securityValidation, etc.) must
     * NOT change the cache key. Two callers that differ only in
     * runtime-validation flags reuse the cached parse result, because
     * the parsed document is identical.
     */
    #[Test]
    public function runtime_validation_toggles_do_not_invalidate_cache(): void
    {
        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->enableCoercion()
            ->enableNullableAsType()
            ->enableStrictFormats()
            ->enableSecurityValidation()
            ->withCache($cache)
            ->build();

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->disableNullableAsType()
            ->withCache($cache)
            ->build();

        $uniqueKeys = $this->uniqueKeys($pool->capturedKeys());

        self::assertCount(
            1,
            $uniqueKeys,
            'Runtime-validation toggles (coercion, nullableAsType, strictFormats, ...) must '
            . 'NOT invalidate the cache key: the parsed document is identical regardless of '
            . 'these flags. Including them would cause spurious cache-misses on every toggle.',
        );
    }

    /**
     * @param list<string> $keys
     *
     * @return list<string>
     */
    private function uniqueKeys(array $keys): array
    {
        $seen = [];

        foreach ($keys as $key) {
            if (false === array_key_exists($key, $seen)) {
                $seen[$key] = true;
            }
        }

        return array_keys($seen);
    }

    private function invokeGenerateCacheKeyFromFile(string $path, string $content): string
    {
        $builder = OpenApiValidatorBuilder::create();
        $method = new ReflectionMethod($builder, 'generateCacheKeyFromFile');

        /** @var string $result */
        $result = $method->invoke($builder, $path, $content);

        return $result;
    }

    private function writeTempSpec(string $content, ?string $path = null): string
    {
        if (null === $path) {
            $existing = glob(sys_get_temp_dir() . '/openapi_cache_key_test_*') ?: [];
            $path = sys_get_temp_dir() . '/openapi_cache_key_test_' . sha1((string) count($existing)) . '.yaml';
        }

        file_put_contents($path, $content);

        return $path;
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

final class KeyCapturingPool implements CacheItemPoolInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var list<string> */
    private array $keys = [];

    public function getItem(string $key): CacheItemInterface
    {
        $this->keys[] = $key;

        if (array_key_exists($key, $this->values)) {
            return new CapturedItem($key, $this->values[$key], true);
        }

        return new CapturedItem($key, null, false);
    }

    /**
     * @param list<string> $keys
     *
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
        return array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];
        $this->keys = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->values[$item->getKey()] = $item->get();

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

    /**
     * @return list<string>
     */
    public function capturedKeys(): array
    {
        return $this->keys;
    }
}

final class CapturedItem implements CacheItemInterface
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
