<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\Internal\CacheKeyBuilder;
use Duyler\OpenApi\Builder\Internal\SpecLoader;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;

/**
 * @internal
 */
final class SpecLoaderTest extends TestCase
{
    private const string MINIMAL_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: SpecLoader test
  version: 1.0.0
paths: {}
YAML;

    private const string MINIMAL_JSON = <<<'JSON'
{
  "openapi": "3.2.0",
  "info": { "title": "SpecLoader test", "version": "1.0.0" },
  "paths": {}
}
JSON;

    #[Test]
    public function load_without_spec_source_throws(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec not loaded');

        $this->buildLoader(new BuilderConfig())->load();
    }

    #[Test]
    public function load_from_yaml_string_returns_document(): void
    {
        $document = $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_YAML,
            specType: 'yaml',
        ))->load();

        self::assertSame('3.2.0', $document->openapi);
    }

    #[Test]
    public function load_from_json_string_returns_document(): void
    {
        $document = $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_JSON,
            specType: 'json',
        ))->load();

        self::assertSame('3.2.0', $document->openapi);
    }

    #[Test]
    public function load_from_yaml_file_returns_document(): void
    {
        $path = $this->writeTempFile(self::MINIMAL_YAML);

        try {
            $document = $this->buildLoader(new BuilderConfig(
                specPath: $path,
                specType: 'yaml',
            ))->load();

            self::assertSame('3.2.0', $document->openapi);
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function load_from_json_file_returns_document(): void
    {
        $path = $this->writeTempFile(self::MINIMAL_JSON);

        try {
            $document = $this->buildLoader(new BuilderConfig(
                specPath: $path,
                specType: 'json',
            ))->load();

            self::assertSame('3.2.0', $document->openapi);
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function load_from_missing_file_throws(): void
    {
        $missing = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duyler-missing-' . uniqid('', true) . '.yaml';

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec file does not exist');

        $this->buildLoader(new BuilderConfig(
            specPath: $missing,
            specType: 'yaml',
        ))->load();
    }

    #[Test]
    public function load_from_malformed_yaml_string_throws_builder_exception(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Failed to parse spec');

        $this->buildLoader(new BuilderConfig(
            specContent: "openapi: 3.2.0\n  bad: indent\n - broken",
            specType: 'yaml',
        ))->load();
    }

    #[Test]
    public function load_from_malformed_json_string_throws_builder_exception(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Failed to parse spec');

        $this->buildLoader(new BuilderConfig(
            specContent: '{"openapi":}',
            specType: 'json',
        ))->load();
    }

    #[Test]
    public function load_with_max_spec_size_violation_propagates_spec_too_large(): void
    {
        $padding = str_repeat('a', 1024);
        $oversized = "openapi: 3.2.0\ninfo:\n  title: {$padding}\n  version: 1.0.0\npaths: {}\n";

        $this->expectException(SpecTooLargeException::class);

        $this->buildLoader(new BuilderConfig(
            specContent: $oversized,
            specType: 'yaml',
            maxSpecSizeBytes: 64,
        ))->load();
    }

    #[Test]
    public function load_with_max_spec_depth_violation_propagates_spec_too_large(): void
    {
        $lines = ['openapi: 3.2.0', 'info:', '  title: Test', '  version: 1.0.0', 'paths: {}', 'components:', '  schemas:', '    Deep:'];
        $current = '      ';
        for ($i = 0; $i < 30; ++$i) {
            $current .= '  ';
            $lines[] = $current . 'nested:';
        }

        $this->expectException(SpecTooLargeException::class);

        $this->buildLoader(new BuilderConfig(
            specContent: implode("\n", $lines),
            specType: 'yaml',
            maxSpecDepth: 10,
        ))->load();
    }

    #[Test]
    public function load_caches_document_on_first_call_for_string_spec(): void
    {
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);

        $loader = $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_YAML,
            specType: 'yaml',
            cache: $cache,
        ));

        $first = $loader->load();
        $secondLoader = $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_YAML,
            specType: 'yaml',
            cache: $cache,
        ));
        $second = $secondLoader->load();

        self::assertSame(spl_object_id($first), spl_object_id($second), 'Second load must return the cached instance (cache hit).');
        self::assertSame(1, $pool->writeCount, 'Document must be persisted exactly once across the two loads.');
        self::assertGreaterThanOrEqual(2, $pool->readCount, 'Each load must consult the cache (cache.get call).');
    }

    #[Test]
    public function load_caches_document_on_first_call_for_file_spec(): void
    {
        $path = $this->writeTempFile(self::MINIMAL_YAML);

        try {
            $pool = new Psr6ArrayCacheStub();
            $cache = new SchemaCache($pool);

            $first = $this->buildLoader(new BuilderConfig(
                specPath: $path,
                specType: 'yaml',
                cache: $cache,
            ))->load();
            $second = $this->buildLoader(new BuilderConfig(
                specPath: $path,
                specType: 'yaml',
                cache: $cache,
            ))->load();

            self::assertSame(spl_object_id($first), spl_object_id($second));
            self::assertSame(1, $pool->writeCount);
        } finally {
            $this->safeUnlink($path);
        }
    }

    #[Test]
    public function load_with_distinct_fingerprint_misses_cache(): void
    {
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);

        $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_YAML,
            specType: 'yaml',
            maxSpecDepth: 50,
            cache: $cache,
        ))->load();
        $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_YAML,
            specType: 'yaml',
            maxSpecDepth: 5,
            cache: $cache,
        ))->load();

        self::assertSame(2, $pool->writeCount, 'Different parse-config fingerprint must produce cache-miss on the second call.');
    }

    #[Test]
    public function load_with_unsupported_spec_type_throws(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Unsupported spec type');

        $this->buildLoader(new BuilderConfig(
            specContent: self::MINIMAL_YAML,
            specType: 'xml',
        ))->load();
    }

    private function buildLoader(BuilderConfig $config): SpecLoader
    {
        return new SpecLoader($config, new CacheKeyBuilder($config));
    }

    private function writeTempFile(string $content): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'duyler-specloader-' . uniqid('', true) . '.yaml';
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
