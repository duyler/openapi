<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Registry;

use Duyler\OpenApi\Registry\Exception\VersionNotFoundException;
use Duyler\OpenApi\Registry\SchemaRegistry;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SchemaRegistryTest extends TestCase
{
    #[Test]
    public function register_adds_schema(): void
    {
        $registry = new SchemaRegistry();
        $document = $this->createDocument();

        $registry = $registry->register('test', '1.0.0', $document);

        self::assertTrue($registry->has('test', '1.0.0'));
    }

    #[Test]
    public function register_returns_new_instance(): void
    {
        $registry1 = new SchemaRegistry();
        $document = $this->createDocument();

        $registry2 = $registry1->register('test', '1.0.0', $document);

        self::assertNotSame($registry1, $registry2);
    }

    #[Test]
    public function get_returns_registered_schema(): void
    {
        $registry = new SchemaRegistry();
        $document = $this->createDocument();

        $registry = $registry->register('test', '1.0.0', $document);
        $retrieved = $registry->get('test', '1.0.0');

        self::assertSame($document, $retrieved);
    }

    #[Test]
    public function get_returns_null_for_non_existent_schema(): void
    {
        $registry = new SchemaRegistry();

        $result = $registry->get('nonexistent', '1.0.0');

        self::assertNull($result);
    }

    #[Test]
    public function get_without_version_returns_latest_version(): void
    {
        $registry = new SchemaRegistry();
        $doc1 = $this->createDocument();
        $doc2 = $this->createDocument();
        $doc3 = $this->createDocument();

        $registry = $registry
            ->register('test', '1.0.0', $doc1)
            ->register('test', '1.1.0', $doc2)
            ->register('test', '2.0.0', $doc3);

        $retrieved = $registry->get('test');

        self::assertSame($doc3, $retrieved);
    }

    #[Test]
    public function get_without_version_sorts_versions_correctly(): void
    {
        $registry = new SchemaRegistry();
        $doc1 = $this->createDocument();
        $doc2 = $this->createDocument();
        $doc3 = $this->createDocument();
        $doc4 = $this->createDocument();

        $registry = $registry
            ->register('test', '2.1.0', $doc1)
            ->register('test', '1.5.10', $doc2)
            ->register('test', '1.10.0', $doc3)
            ->register('test', '2.0.5', $doc4);

        $retrieved = $registry->get('test');

        self::assertSame($doc1, $retrieved);
    }

    #[Test]
    public function get_versions_returns_sorted_versions(): void
    {
        $registry = new SchemaRegistry();
        $doc1 = $this->createDocument();
        $doc2 = $this->createDocument();
        $doc3 = $this->createDocument();

        $registry = $registry
            ->register('test', '2.0.0', $doc1)
            ->register('test', '1.0.0', $doc2)
            ->register('test', '1.5.0', $doc3);

        $versions = $registry->getVersions('test');

        self::assertSame(['1.0.0', '1.5.0', '2.0.0'], $versions);
    }

    #[Test]
    public function get_names_returns_all_registered_names(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('schema1', '1.0.0', $doc)
            ->register('schema2', '1.0.0', $doc)
            ->register('schema3', '1.0.0', $doc);

        $names = $registry->getNames();

        self::assertSame(['schema1', 'schema2', 'schema3'], $names);
    }

    #[Test]
    public function count_returns_number_of_schemas(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('schema1', '1.0.0', $doc)
            ->register('schema2', '1.0.0', $doc)
            ->register('schema3', '1.0.0', $doc);

        self::assertSame(3, $registry->count());
    }

    #[Test]
    public function count_versions_returns_number_of_versions_for_schema(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('test', '1.0.0', $doc)
            ->register('test', '1.5.0', $doc)
            ->register('test', '2.0.0', $doc);

        self::assertSame(3, $registry->countVersions('test'));
    }

    #[Test]
    public function has_returns_true_when_any_version_exists(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('test', '1.0.0', $doc)
            ->register('test', '2.0.0', $doc);

        $result = $registry->has('test');

        self::assertTrue($result);
    }

    #[Test]
    public function has_returns_false_when_schema_not_exists(): void
    {
        $registry = new SchemaRegistry();

        $result = $registry->has('nonexistent');

        self::assertFalse($result);
    }

    #[Test]
    public function get_without_version_returns_null_for_nonexistent_schema(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry->register('test', '1.0.0', $doc);

        $versions = $registry->getVersions('test');
        self::assertNotEmpty($versions);

        $retrieved = $registry->get('nonexistent');

        self::assertNull($retrieved);
    }

    #[Test]
    public function register_same_version_overwrites_document(): void
    {
        $registry = new SchemaRegistry();
        $docV1 = $this->createDocumentWithTitle('First API');
        $docV2 = $this->createDocumentWithTitle('Second API');

        $registry = $registry->register('api', '1.0.0', $docV1);
        $registry = $registry->register('api', '1.0.0', $docV2);

        $retrieved = $registry->get('api', '1.0.0');

        self::assertSame($docV2, $retrieved);
        self::assertNotSame($docV1, $retrieved);
    }

    #[Test]
    public function overwrite_preserves_other_versions(): void
    {
        $registry = new SchemaRegistry();
        $docV1Original = $this->createDocumentWithTitle('API v1 original');
        $docV2 = $this->createDocumentWithTitle('API v2');
        $docV1Overwrite = $this->createDocumentWithTitle('API v1 overwrite');

        $registry = $registry
            ->register('api', '1.0.0', $docV1Original)
            ->register('api', '2.0.0', $docV2)
            ->register('api', '1.0.0', $docV1Overwrite);

        $retrievedV1 = $registry->get('api', '1.0.0');
        $retrievedV2 = $registry->get('api', '2.0.0');
        $versions = $registry->getVersions('api');

        self::assertSame($docV1Overwrite, $retrievedV1);
        self::assertSame($docV2, $retrievedV2);
        self::assertSame(['1.0.0', '2.0.0'], $versions);
        self::assertSame(2, $registry->countVersions('api'));
    }

    #[Test]
    public function overwrite_does_not_mutate_original_instance(): void
    {
        $registry = new SchemaRegistry();
        $docV1 = $this->createDocumentWithTitle('First');
        $docV2 = $this->createDocumentWithTitle('Second');

        $registryV1 = $registry->register('api', '1.0.0', $docV1);
        $registryV2 = $registryV1->register('api', '1.0.0', $docV2);

        $originalAfterOverwrite = $registryV1->get('api', '1.0.0');
        $newAfterOverwrite = $registryV2->get('api', '1.0.0');

        self::assertSame($docV1, $originalAfterOverwrite);
        self::assertSame($docV2, $newAfterOverwrite);
    }

    #[Test]
    public function get_returns_null_for_non_existent_version_when_other_versions_exist(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry->register('api', '1.0.0', $doc);

        $retrieved = $registry->get('api', '999.0.0');

        self::assertNull($retrieved);
    }

    #[Test]
    public function get_or_fail_returns_document_for_existing_version(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry->register('api', '1.0.0', $doc);

        $retrieved = $registry->getOrFail('api', '1.0.0');

        self::assertSame($doc, $retrieved);
    }

    #[Test]
    public function get_or_fail_returns_latest_version_when_no_version_given(): void
    {
        $registry = new SchemaRegistry();
        $docV1 = $this->createDocumentWithTitle('v1');
        $docV2 = $this->createDocumentWithTitle('v2');

        $registry = $registry
            ->register('api', '1.0.0', $docV1)
            ->register('api', '2.0.0', $docV2);

        $retrieved = $registry->getOrFail('api');

        self::assertSame($docV2, $retrieved);
    }

    #[Test]
    public function get_or_fail_throws_for_non_existent_version_when_other_versions_exist(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry->register('api', '1.0.0', $doc);

        $this->expectException(VersionNotFoundException::class);
        $this->expectExceptionMessage('Schema "api" does not have version "999.0.0"');

        $registry->getOrFail('api', '999.0.0');
    }

    #[Test]
    public function get_or_fail_throws_for_non_existent_schema_with_version(): void
    {
        $registry = new SchemaRegistry();

        $this->expectException(VersionNotFoundException::class);
        $this->expectExceptionMessage('Schema "unknown" does not have version "1.0.0"');

        $registry->getOrFail('unknown', '1.0.0');
    }

    #[Test]
    public function get_or_fail_throws_for_non_existent_schema_without_version(): void
    {
        $registry = new SchemaRegistry();

        $this->expectException(VersionNotFoundException::class);
        $this->expectExceptionMessage('Schema "unknown" has no registered versions');

        $registry->getOrFail('unknown');
    }

    #[Test]
    public function get_or_fail_throws_for_empty_registry_when_latest_version_requested(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry->register('api', '1.0.0', $doc);

        $this->expectException(VersionNotFoundException::class);
        $this->expectExceptionMessage('Schema "missing" has no registered versions');

        $registry->getOrFail('missing');
    }

    #[Test]
    public function version_not_found_exception_extends_runtime_exception(): void
    {
        $registry = new SchemaRegistry();

        try {
            $registry->getOrFail('api', '1.0.0');
            self::fail('Expected VersionNotFoundException to be thrown');
        } catch (VersionNotFoundException $exception) {
            self::assertInstanceOf(RuntimeException::class, $exception);
            self::assertSame('Schema "api" does not have version "1.0.0"', $exception->getMessage());
        }
    }

    private function createDocument(): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(
                title: 'Test API',
                version: '1.0.0',
            ),
        );
    }

    private function createDocumentWithTitle(string $title): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(
                title: $title,
                version: '1.0.0',
            ),
        );
    }
}
