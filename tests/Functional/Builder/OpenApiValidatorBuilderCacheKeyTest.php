<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function count;
use function file_put_contents;
use function glob;
use function hash;
use function is_file;
use function sha1;
use function str_ends_with;
use function strlen;
use function sys_get_temp_dir;
use function time;
use function touch;
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
    private const string CONFINEMENT_KEY_SUFFIX = '_confinement';

    #[Test]
    public function string_cache_key_uses_sha256_length(): void
    {
        $pool = new Psr6ArrayCacheStub();
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
        $pool = new Psr6ArrayCacheStub();
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
        $pool = new Psr6ArrayCacheStub();
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
            $pool = new Psr6ArrayCacheStub();
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
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool1 = new Psr6ArrayCacheStub();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool1))
                ->build();
            $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];

            touch($path, time() + 100);

            $pool2 = new Psr6ArrayCacheStub();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool2))
                ->build();
            $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

            self::assertSame(
                $key1,
                $key2,
                'Cache key must ignore mtime after content-hash fix (defends against touch -r cache-poisoning)',
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
            $pool1 = new Psr6ArrayCacheStub();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool1))
                ->build();
            $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];

            $appended = self::MINIMAL_YAML . PHP_EOL . '# extra comment to change size' . PHP_EOL;
            $this->writeTempSpec($appended, $path);
            touch($path, time() + 200);

            $pool2 = new Psr6ArrayCacheStub();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool2))
                ->build();
            $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

            self::assertNotSame($key1, $key2, 'Cache key must change when file content changes (content-hash fix)');
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function file_cache_key_stable_across_rebuilds_with_unchanged_file(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool1 = new Psr6ArrayCacheStub();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool1))
                ->build();
            $key1 = $this->uniqueKeys($pool1->capturedKeys())[0];

            $pool2 = new Psr6ArrayCacheStub();
            OpenApiValidatorBuilder::create()
                ->fromYamlFile($path)
                ->withCache(new SchemaCache($pool2))
                ->build();
            $key2 = $this->uniqueKeys($pool2->capturedKeys())[0];

            self::assertSame($key1, $key2, 'Same file content must produce identical cache key (content-hash fix)');
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function file_cache_key_is_not_xxh64_length(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $pool = new Psr6ArrayCacheStub();
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

        $pool = new Psr6ArrayCacheStub();
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
            . 're-parse that rejects the depth-6 spec (cache-poisoning defence).',
        );
    }

    #[Test]
    public function external_ref_allowed_root_change_produces_distinct_cache_keys(): void
    {
        $root1 = sys_get_temp_dir();
        $root2 = dirname(__DIR__, 3);

        $pool1 = new Psr6ArrayCacheStub();
        OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withExternalRefAllowedRoot($root1)
            ->withCache(new SchemaCache($pool1))
            ->build();

        $pool2 = new Psr6ArrayCacheStub();
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
            . 'produce a distinct cache key (confinement poisoning defence).',
        );
    }

    #[Test]
    public function identical_parse_config_and_content_reuse_cache_key(): void
    {
        $pool = new Psr6ArrayCacheStub();
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

    #[Test]
    public function runtime_validation_toggles_do_not_invalidate_cache(): void
    {
        $pool = new Psr6ArrayCacheStub();
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
     * @return list<string>
     */
    private function uniqueKeys(array $keys): array
    {
        $seen = [];

        foreach ($keys as $key) {
            if (str_ends_with($key, self::CONFINEMENT_KEY_SUFFIX)) {
                continue;
            }

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
