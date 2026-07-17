<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Registry;

use Duyler\OpenApi\Registry\Exception\SchemaAlreadyRegisteredException;
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
    public function count_names_returns_distinct_names_count(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('schema1', '1.0.0', $doc)
            ->register('schema2', '1.0.0', $doc)
            ->register('schema3', '1.0.0', $doc);

        self::assertSame(3, $registry->countNames());
    }

    #[Test]
    public function count_schemas_returns_total_pairs(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('api', '1.0.0', $doc)
            ->register('api', '2.0.0', $doc)
            ->register('web', '1.0.0', $doc);

        self::assertSame(2, $registry->countNames());
        self::assertSame(3, $registry->countSchemas());
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
    public function register_throws_on_duplicate_name_version(): void
    {
        $registry = new SchemaRegistry();
        $docV1 = $this->createDocumentWithTitle('First API');
        $docV2 = $this->createDocumentWithTitle('Second API');

        $registry = $registry->register('api', '1.0.0', $docV1);

        $this->expectException(SchemaAlreadyRegisteredException::class);
        $this->expectExceptionMessage('Schema "api" version "1.0.0" is already registered');

        $registry->register('api', '1.0.0', $docV2);
    }

    #[Test]
    public function schema_already_registered_exception_exposes_name_and_version(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry->register('api', '1.0.0', $doc);

        try {
            $registry->register('api', '1.0.0', $doc);
            self::fail('Expected SchemaAlreadyRegisteredException to be thrown');
        } catch (SchemaAlreadyRegisteredException $exception) {
            self::assertSame('api', $exception->name);
            self::assertSame('1.0.0', $exception->version);
            self::assertInstanceOf(RuntimeException::class, $exception);
        }
    }

    #[Test]
    public function register_or_replace_overwrites_silently(): void
    {
        $registry = new SchemaRegistry();
        $docV1 = $this->createDocumentWithTitle('First API');
        $docV2 = $this->createDocumentWithTitle('Second API');

        $registry = $registry->registerOrReplace('api', '1.0.0', $docV1);
        $registry = $registry->registerOrReplace('api', '1.0.0', $docV2);

        $retrieved = $registry->get('api', '1.0.0');

        self::assertSame($docV2, $retrieved);
        self::assertNotSame($docV1, $retrieved);
    }

    #[Test]
    public function register_or_replace_preserves_other_versions(): void
    {
        $registry = new SchemaRegistry();
        $docV1Original = $this->createDocumentWithTitle('API v1 original');
        $docV2 = $this->createDocumentWithTitle('API v2');
        $docV1Overwrite = $this->createDocumentWithTitle('API v1 overwrite');

        $registry = $registry
            ->register('api', '1.0.0', $docV1Original)
            ->register('api', '2.0.0', $docV2)
            ->registerOrReplace('api', '1.0.0', $docV1Overwrite);

        $retrievedV1 = $registry->get('api', '1.0.0');
        $retrievedV2 = $registry->get('api', '2.0.0');
        $versions = $registry->getVersions('api');

        self::assertSame($docV1Overwrite, $retrievedV1);
        self::assertSame($docV2, $retrievedV2);
        self::assertSame(['1.0.0', '2.0.0'], $versions);
        self::assertSame(2, $registry->countVersions('api'));
    }

    #[Test]
    public function register_or_replace_does_not_mutate_original_instance(): void
    {
        $registry = new SchemaRegistry();
        $docV1 = $this->createDocumentWithTitle('First');
        $docV2 = $this->createDocumentWithTitle('Second');

        $registryV1 = $registry->register('api', '1.0.0', $docV1);
        $registryV2 = $registryV1->registerOrReplace('api', '1.0.0', $docV2);

        $originalAfterOverwrite = $registryV1->get('api', '1.0.0');
        $newAfterOverwrite = $registryV2->get('api', '1.0.0');

        self::assertSame($docV1, $originalAfterOverwrite);
        self::assertSame($docV2, $newAfterOverwrite);
    }

    #[Test]
    public function register_is_chainable_like_before(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $newRegistry = $registry
            ->register('api', '1.0.0', $doc)
            ->register('api', '2.0.0', $doc)
            ->register('web', '1.0.0', $doc);

        self::assertNotSame($registry, $newRegistry);
        self::assertSame(['api', 'web'], $newRegistry->getNames());
        self::assertSame(['1.0.0', '2.0.0'], $newRegistry->getVersions('api'));
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

    /**
     * RG-02: Pre-release versions follow semver ordering rules.
     *
     * Per semver 2.0.0 §9-10, pre-release versions have lower precedence
     * than the associated normal version: 1.0.0-alpha < 1.0.0-beta < 1.0.0.
     * version_compare() in PHP follows the same semantics.
     */
    #[Test]
    public function pre_release_versions_sorted_correctly(): void
    {
        $registry = new SchemaRegistry();
        $docAlpha = $this->createDocumentWithTitle('alpha');
        $docBeta = $this->createDocumentWithTitle('beta');
        $docRc = $this->createDocumentWithTitle('rc');
        $docStable = $this->createDocumentWithTitle('stable');

        $registry = $registry
            ->register('api', '2.0.0-rc.1', $docRc)
            ->register('api', '1.0.0-alpha', $docAlpha)
            ->register('api', '1.0.0', $docStable)
            ->register('api', '1.0.0-beta.1', $docBeta);

        $versions = $registry->getVersions('api');

        self::assertSame(
            ['1.0.0-alpha', '1.0.0-beta.1', '1.0.0', '2.0.0-rc.1'],
            $versions,
        );
    }

    /**
     * RG-02: get('api') with no version returns the latest non-pre-release
     * version (the highest semver), not the highest pre-release.
     */
    #[Test]
    public function get_latest_returns_stable_release_over_pre_release(): void
    {
        $registry = new SchemaRegistry();
        $docAlpha = $this->createDocumentWithTitle('alpha');
        $docStable = $this->createDocumentWithTitle('stable');

        $registry = $registry
            ->register('api', '1.0.0-alpha', $docAlpha)
            ->register('api', '1.0.0', $docStable);

        $latest = $registry->get('api');

        self::assertSame($docStable, $latest);
        self::assertNotSame($docAlpha, $latest);
    }

    /**
     * RG-02: When only pre-release versions are registered, get('api')
     * returns the highest pre-release.
     */
    #[Test]
    public function get_latest_returns_highest_pre_release_when_only_pre_releases_registered(): void
    {
        $registry = new SchemaRegistry();
        $docAlpha = $this->createDocumentWithTitle('alpha');
        $docBeta = $this->createDocumentWithTitle('beta');

        $registry = $registry
            ->register('api', '1.0.0-alpha', $docAlpha)
            ->register('api', '1.0.0-beta.1', $docBeta);

        $latest = $registry->get('api');

        self::assertSame($docBeta, $latest);
    }

    /**
     * RG-02: Specific pre-release version lookup works by explicit version.
     */
    #[Test]
    public function get_specific_pre_release_version(): void
    {
        $registry = new SchemaRegistry();
        $docAlpha = $this->createDocumentWithTitle('alpha');
        $docRc = $this->createDocumentWithTitle('rc');

        $registry = $registry
            ->register('api', '1.0.0-alpha', $docAlpha)
            ->register('api', '2.0.0-rc.1', $docRc);

        self::assertSame($docAlpha, $registry->get('api', '1.0.0-alpha'));
        self::assertSame($docRc, $registry->get('api', '2.0.0-rc.1'));
    }

    /**
     * RG-02: Immutability — registering a pre-release does not mutate
     * the original instance.
     */
    #[Test]
    public function register_pre_release_returns_new_instance_and_preserves_original(): void
    {
        $registry = new SchemaRegistry();
        $docAlpha = $this->createDocumentWithTitle('alpha');

        $newRegistry = $registry->register('api', '1.0.0-alpha', $docAlpha);

        self::assertNotSame($registry, $newRegistry);
        self::assertFalse($registry->has('api', '1.0.0-alpha'));
        self::assertTrue($newRegistry->has('api', '1.0.0-alpha'));
    }

    /**
     * RG-02: Pre-release mixing with multiple stable versions.
     */
    #[Test]
    public function pre_release_mixed_with_multiple_stable_versions(): void
    {
        $registry = new SchemaRegistry();
        $doc1Alpha = $this->createDocumentWithTitle('1.0.0-alpha');
        $doc1 = $this->createDocumentWithTitle('1.0.0');
        $doc2Beta = $this->createDocumentWithTitle('2.0.0-beta');
        $doc2 = $this->createDocumentWithTitle('2.0.0');

        $registry = $registry
            ->register('api', '2.0.0-beta', $doc2Beta)
            ->register('api', '1.0.0-alpha', $doc1Alpha)
            ->register('api', '2.0.0', $doc2)
            ->register('api', '1.0.0', $doc1);

        $versions = $registry->getVersions('api');

        self::assertSame(
            ['1.0.0-alpha', '1.0.0', '2.0.0-beta', '2.0.0'],
            $versions,
        );

        self::assertSame($doc2, $registry->get('api'));
        self::assertSame(4, $registry->countVersions('api'));
    }

    /**
     * RG-03: countVersions returns 0 for a name that has never been
     * registered, instead of throwing an exception.
     */
    #[Test]
    public function count_versions_returns_zero_for_nonexistent_name(): void
    {
        $registry = new SchemaRegistry();

        self::assertSame(0, $registry->countVersions('nonexistent'));
    }

    /**
     * RG-03: countVersions returns 0 for a nonexistent name even when
     * other schemas are registered.
     */
    #[Test]
    public function count_versions_returns_zero_for_nonexistent_name_when_others_registered(): void
    {
        $registry = new SchemaRegistry();
        $doc = $this->createDocument();

        $registry = $registry
            ->register('api', '1.0.0', $doc)
            ->register('api', '2.0.0', $doc);

        self::assertSame(2, $registry->countVersions('api'));
        self::assertSame(0, $registry->countVersions('nonexistent'));
    }

    /**
     * RG-03: getVersions returns an empty array (not null) for a
     * nonexistent name.
     */
    #[Test]
    public function get_versions_returns_empty_array_for_nonexistent_name(): void
    {
        $registry = new SchemaRegistry();

        $versions = $registry->getVersions('nonexistent');

        self::assertSame([], $versions);
    }

    /**
     * RG-03: get('nonexistent') with no version returns null and does
     * not throw.
     */
    #[Test]
    public function get_returns_null_for_nonexistent_name_with_no_version(): void
    {
        $registry = new SchemaRegistry();

        self::assertNull($registry->get('nonexistent'));
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
