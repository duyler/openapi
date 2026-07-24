<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Internal\CacheKeyBuilder;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function glob;
use function hash;
use function is_file;
use function sha1;
use function strlen;
use function sys_get_temp_dir;
use function touch;
use function unlink;

use function count;

use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
final class CacheKeyBuilderTest extends TestCase
{
    private const string MINIMAL_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Cache Key Test
  version: 1.0.0
paths: {}
YAML;

    private const string DEFAULT_FINGERPRINT = 'maxSpecDepth=100|maxSpecSizeBytes=1048576|externalRefAllowedRoot=|externalRefMaxBytes=10485760';

    #[Test]
    public function for_string_returns_content_prefix_with_sha256_hash(): void
    {
        $key = $this->defaultBuilder()->forString(self::MINIMAL_YAML);

        self::assertStringStartsWith(CacheKeyBuilder::CONTENT_PREFIX, $key);

        $hash = substr($key, strlen(CacheKeyBuilder::CONTENT_PREFIX));
        self::assertSame(64, strlen($hash), 'SHA-256 hex digest must be 64 chars, not 16 (xxh64)');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function for_string_uses_sha256_of_content_hash_concatenated_with_fingerprint(): void
    {
        $key = $this->defaultBuilder()->forString(self::MINIMAL_YAML);

        $expected = CacheKeyBuilder::CONTENT_PREFIX
            . hash('sha256', hash('sha256', self::MINIMAL_YAML) . '|' . self::DEFAULT_FINGERPRINT);

        self::assertSame($expected, $key);
    }

    #[Test]
    public function for_string_hash_is_not_xxh64_length(): void
    {
        $key = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $hash = substr($key, strlen(CacheKeyBuilder::CONTENT_PREFIX));

        self::assertNotSame(16, strlen($hash), 'Cache key must not be xxh64 (16 hex chars)');
    }

    #[Test]
    public function for_file_returns_file_prefix_with_sha256_hash(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $key = $this->defaultBuilder()->forFile($path, self::MINIMAL_YAML);

            self::assertStringStartsWith(CacheKeyBuilder::FILE_PREFIX, $key);

            $hash = substr($key, strlen(CacheKeyBuilder::FILE_PREFIX));
            self::assertSame(64, strlen($hash));
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function for_file_distinct_keys_for_distinct_content_same_path(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $keyForContentA = $this->defaultBuilder()->forFile($path, 'openapi: 3.2.0 a');
            $keyForContentB = $this->defaultBuilder()->forFile($path, 'openapi: 3.2.0 b');

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
    public function for_file_distinct_keys_for_distinct_paths_same_content(): void
    {
        $pathA = $this->writeTempSpec(self::MINIMAL_YAML, $this->uniqueTempPath('a'));
        $pathB = $this->writeTempSpec(self::MINIMAL_YAML, $this->uniqueTempPath('b'));

        try {
            $keyForPathA = $this->defaultBuilder()->forFile($pathA, self::MINIMAL_YAML);
            $keyForPathB = $this->defaultBuilder()->forFile($pathB, self::MINIMAL_YAML);

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
    public function for_file_invariant_under_mtime_changes(): void
    {
        $path = $this->writeTempSpec(self::MINIMAL_YAML);

        try {
            $keyBefore = $this->defaultBuilder()->forFile($path, self::MINIMAL_YAML);

            touch($path, time() + 500);

            $keyAfter = $this->defaultBuilder()->forFile($path, self::MINIMAL_YAML);

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
    public function for_file_falls_back_to_unresolved_path_when_realpath_fails(): void
    {
        $nonexistentPath = sys_get_temp_dir() . '/duyler-cache-key-missing-' . sha1((string) time()) . '.yaml';

        $key = $this->defaultBuilder()->forFile($nonexistentPath, self::MINIMAL_YAML);

        self::assertStringStartsWith(CacheKeyBuilder::FILE_PREFIX, $key);
        $hash = substr($key, strlen(CacheKeyBuilder::FILE_PREFIX));
        self::assertSame(64, strlen($hash), 'Fallback key must still be a SHA-256 hex digest');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    #[Test]
    public function max_spec_depth_change_produces_distinct_keys(): void
    {
        $keyDefault = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $keyStrict = $this->builderWith(maxSpecDepth: 5)->forString(self::MINIMAL_YAML);

        self::assertNotSame(
            $keyDefault,
            $keyStrict,
            'maxSpecDepth change must produce a distinct cache key (R4-SEC-008 cache-poisoning defence).',
        );
    }

    #[Test]
    public function max_spec_size_change_produces_distinct_keys(): void
    {
        $keyDefault = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $keyStrict = $this->builderWith(maxSpecSizeBytes: 512)->forString(self::MINIMAL_YAML);

        self::assertNotSame(
            $keyDefault,
            $keyStrict,
            'maxSpecSizeBytes change must produce a distinct cache key (R4-SEC-008 cache-poisoning defence).',
        );
    }

    #[Test]
    public function external_ref_allowed_root_change_produces_distinct_keys(): void
    {
        $keyWithoutRoot = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $keyWithRoot = $this->builderWith(externalRefAllowedRoot: '/var/specs')->forString(self::MINIMAL_YAML);

        self::assertNotSame(
            $keyWithoutRoot,
            $keyWithRoot,
            'externalRefAllowedRoot change must produce a distinct cache key (R4-SEC-008 confinement poisoning defence).',
        );
    }

    #[Test]
    public function external_ref_max_bytes_change_produces_distinct_keys(): void
    {
        $keyDefault = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $keyStrict = $this->builderWith(externalRefMaxBytes: 1024)->forString(self::MINIMAL_YAML);

        self::assertNotSame(
            $keyDefault,
            $keyStrict,
            'externalRefMaxBytes change must produce a distinct cache key (R4-SEC-008 cache-poisoning defence).',
        );
    }

    #[Test]
    public function identical_config_and_content_produce_identical_keys(): void
    {
        $key1 = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $key2 = $this->defaultBuilder()->forString(self::MINIMAL_YAML);

        self::assertSame($key1, $key2);
    }

    #[Test]
    public function build_fingerprint_includes_all_four_config_components(): void
    {
        $fingerprint = 'maxSpecDepth=100|maxSpecSizeBytes=1048576|externalRefAllowedRoot=|externalRefMaxBytes=10485760';

        $key = $this->defaultBuilder()->forString(self::MINIMAL_YAML);
        $expected = CacheKeyBuilder::CONTENT_PREFIX
            . hash('sha256', hash('sha256', self::MINIMAL_YAML) . '|' . $fingerprint);

        self::assertSame($expected, $key);
        self::assertSame(
            FileExternalRefResolver::DEFAULT_MAX_REF_BYTES,
            10_485_760,
            'Pin default external-ref byte cap: if this constant changes the fingerprint default must be updated in this test.',
        );
    }

    private function defaultBuilder(): CacheKeyBuilder
    {
        return new CacheKeyBuilder(new BuilderConfig());
    }

    private function builderWith(
        ?int $maxSpecDepth = null,
        ?int $maxSpecSizeBytes = null,
        ?string $externalRefAllowedRoot = null,
        ?int $externalRefMaxBytes = null,
    ): CacheKeyBuilder {
        return new CacheKeyBuilder(new BuilderConfig(
            maxSpecDepth: $maxSpecDepth,
            maxSpecSizeBytes: $maxSpecSizeBytes,
            externalRefAllowedRoot: $externalRefAllowedRoot,
            externalRefMaxBytes: $externalRefMaxBytes,
        ));
    }

    private function writeTempSpec(string $content, ?string $path = null): string
    {
        $path ??= $this->uniqueTempPath('default');

        file_put_contents($path, $content);

        return $path;
    }

    private function uniqueTempSpecPath(string $tag): string
    {
        return $this->uniqueTempPath($tag);
    }

    private function uniqueTempPath(string $tag): string
    {
        $existing = glob(sys_get_temp_dir() . '/openapi_cachekey_' . $tag . '_*') ?: [];

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'openapi_cachekey_' . $tag . '_' . sha1((string) count($existing)) . '.yaml';
    }

    private function safeUnlink(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
