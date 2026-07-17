<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Registry;

use Duyler\OpenApi\Registry\SchemaRegistry;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @internal
 *
 * Anti-test for P-053: asserts that SchemaRegistry precomputes the
 * latest-version-per-schema and sorted-versions lists in the
 * constructor, so get($name) without a version and getVersions($name)
 * are O(1) lookups instead of per-call uksort. Reverting to the lazy
 * uksort form removes both private properties, breaking the reflection
 * assertions; get() still returns the right document but no longer in
 * constant time.
 */
final class SchemaRegistryPrecompiledTest extends TestCase
{
    #[Test]
    public function get_latest_returns_in_o1(): void
    {
        $registry = new SchemaRegistry();

        $docV1 = $this->createDocumentWithTitle('v1');
        $docV2 = $this->createDocumentWithTitle('v2');
        $docV3 = $this->createDocumentWithTitle('v3');
        $docV4 = $this->createDocumentWithTitle('v4');
        $docV5 = $this->createDocumentWithTitle('v5');

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

        $apiDoc = $this->createDocumentWithTitle('api');
        $webDoc = $this->createDocumentWithTitle('web');

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

    private function createDocumentWithTitle(string $title): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: $title, version: '1.0.0'),
        );
    }
}
