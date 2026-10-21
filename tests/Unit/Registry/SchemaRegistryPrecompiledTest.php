<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Registry;

use Duyler\OpenApi\Registry\SchemaRegistry;
use Duyler\OpenApi\Test\Support\SpecBuilders;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @internal
 */
final class SchemaRegistryPrecompiledTest extends TestCase
{
    #[Test]
    public function get_latest_returns_in_o1(): void
    {
        $registry = new SchemaRegistry();

        $docV1 = SpecBuilders::documentWithTitle(title: 'v1', openapiVersion: '3.2.0');
        $docV2 = SpecBuilders::documentWithTitle(title: 'v2', openapiVersion: '3.2.0');
        $docV3 = SpecBuilders::documentWithTitle(title: 'v3', openapiVersion: '3.2.0');
        $docV4 = SpecBuilders::documentWithTitle(title: 'v4', openapiVersion: '3.2.0');
        $docV5 = SpecBuilders::documentWithTitle(title: 'v5', openapiVersion: '3.2.0');

        $registry = $registry
            ->register('api', '2.1.0', $docV1)
            ->register('api', '1.5.10', $docV2)
            ->register('api', '1.10.0', $docV3)
            ->register('api', '2.0.5', $docV4)
            ->register('api', '2.2.0', $docV5);

        $latestBySchema = $this->readProperty($registry, 'latestBySchema');
        $sortedVersions = $this->readProperty($registry, 'sortedVersions');

        self::assertArrayHasKey('api', $latestBySchema, 'latestBySchema must be precompiled at construction');
        self::assertSame($docV5, $latestBySchema['api'], 'Precompiled latest must be the highest semver');

        self::assertArrayHasKey('api', $sortedVersions, 'sortedVersions must be precompiled at construction');
        self::assertSame(
            ['1.5.10', '1.10.0', '2.0.5', '2.1.0', '2.2.0'],
            $sortedVersions['api'],
            'Precompiled sorted versions must follow semver order',
        );

        self::assertSame($docV5, $registry->get('api'));
        self::assertSame(
            ['1.5.10', '1.10.0', '2.0.5', '2.1.0', '2.2.0'],
            $registry->getVersions('api'),
        );
    }

    #[Test]
    public function empty_registry_has_empty_precompiled_maps(): void
    {
        $registry = new SchemaRegistry();

        $latestBySchema = $this->readProperty($registry, 'latestBySchema');
        $sortedVersions = $this->readProperty($registry, 'sortedVersions');

        self::assertSame([], $latestBySchema);
        self::assertSame([], $sortedVersions);

        self::assertNull($registry->get('missing'));
        self::assertSame([], $registry->getVersions('missing'));
    }

    #[Test]
    public function precompiled_maps_isolated_per_name(): void
    {
        $registry = new SchemaRegistry();

        $apiDoc = SpecBuilders::documentWithTitle(title: 'api', openapiVersion: '3.2.0');
        $webDoc = SpecBuilders::documentWithTitle(title: 'web', openapiVersion: '3.2.0');

        $registry = $registry
            ->register('api', '1.0.0', $apiDoc)
            ->register('api', '2.0.0', $apiDoc)
            ->register('web', '3.0.0', $webDoc);

        $latestBySchema = $this->readProperty($registry, 'latestBySchema');

        self::assertSame($apiDoc, $latestBySchema['api']);
        self::assertSame($webDoc, $latestBySchema['web']);
    }

    /**
     * @return array<string, mixed>
     */
    private function readProperty(SchemaRegistry $registry, string $name): array
    {
        $property = new ReflectionProperty($registry, $name);

        /** @var array<string, mixed> $value */
        return $property->getValue($registry);
    }
}
