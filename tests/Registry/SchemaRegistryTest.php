<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Registry;

use Duyler\OpenApi\Registry\SchemaRegistry;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
}
