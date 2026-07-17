<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use DateInterval;
use DateTimeInterface;

use function array_key_exists;
use function count;
use function file_put_contents;
use function glob;
use function hash;
use function is_file;
use function sha1;
use function strlen;
use function sys_get_temp_dir;
use function touch;
use function unlink;

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
    public function string_cache_key_matches_raw_sha256_of_content(): void
    {
        $pool = new KeyCapturingPool();
        $cache = new SchemaCache($pool);

        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build();

        $expected = self::CONTENT_KEY_PREFIX . hash('sha256', self::MINIMAL_YAML);
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
    public function file_cache_key_changes_when_mtime_changes(): void
    {
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

            self::assertNotSame($key1, $key2, 'Cache key must change when file mtime changes (silent staleness fix)');
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

            self::assertNotSame($key1, $key2, 'Cache key must change when file size changes');
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

            self::assertSame($key1, $key2, 'Same file with unchanged mtime/size must produce identical cache key');
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
