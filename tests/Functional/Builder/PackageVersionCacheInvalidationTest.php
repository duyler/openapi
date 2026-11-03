<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Builder\Internal\CacheKeyBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageVersionCacheInvalidationTest extends TestCase
{
    private const string MINIMAL_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Package Version Cache Test
  version: 1.0.0
paths: {}
YAML;

    #[Test]
    public function build_does_not_reuse_document_cached_under_previous_package_version(): void
    {
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);

        $loaderConfig = new BuilderConfig(specContent: self::MINIMAL_YAML, specType: 'yaml');
        $previousVersionKey = new CacheKeyBuilder($loaderConfig, '0.10.0')->forString(self::MINIMAL_YAML);
        $currentVersionKey = new CacheKeyBuilder($loaderConfig)->forString(self::MINIMAL_YAML);

        $staleDocument = $this->parseWithoutCache();

        $cache->set($previousVersionKey, $staleDocument);

        $upgradedDocument = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build()
            ->getDocument();

        self::assertNotSame(
            $previousVersionKey,
            $currentVersionKey,
            'Precondition: the key computed under the previous package version must differ from the current-version key.',
        );
        self::assertNotSame(
            $staleDocument,
            $upgradedDocument,
            'A document cached under the previous package version must never be reused after an upgrade.',
        );
        self::assertContains($currentVersionKey, $pool->contentKeys(), 'The fresh parse must be persisted under the current-version key.');
    }

    #[Test]
    public function build_reuses_document_cached_under_current_package_version(): void
    {
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);

        $first = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build()
            ->getDocument();

        $second = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->withCache($cache)
            ->build()
            ->getDocument();

        self::assertInstanceOf(OpenApiDocument::class, $first);
        self::assertSame(
            $first,
            $second,
            'Same content at the same package version must reuse the cached document.',
        );
    }

    private function parseWithoutCache(): ?OpenApiDocument
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MINIMAL_YAML)
            ->build()
            ->getDocument();
    }
}
