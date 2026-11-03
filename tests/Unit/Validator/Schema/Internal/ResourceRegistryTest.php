<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Exception\DuplicateSchemaIdException;
use Duyler\OpenApi\Validator\Schema\Exception\InvalidAnchorException;
use Duyler\OpenApi\Validator\Schema\Exception\SchemaIdFragmentException;
use Duyler\OpenApi\Validator\Schema\Internal\ResourceRegistry;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalker;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalkerInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use WeakMap;

use function gc_collect_cycles;

final class CountingWalker implements SchemaSubschemaWalkerInterface
{
    public int $traversals = 0;

    public function __construct(
        private readonly SchemaSubschemaWalkerInterface $inner = new SchemaSubschemaWalker(),
    ) {}

    public function iterateSubSchemas(Schema $schema): Generator
    {
        ++$this->traversals;

        yield from $this->inner->iterateSubSchemas($schema);
    }

    public function collectSingleSubSchemas(Schema $schema): array
    {
        return $this->inner->collectSingleSubSchemas($schema);
    }
}

/**
 * @internal
 */
final class ResourceRegistryTest extends TestCase
{
    private RefResolver $resolver;

    protected function setUp(): void
    {
        self::resetRegistriesMap();
        $this->resolver = new RefResolver();
    }

    #[Test]
    public function indexes_nested_ids_anchors_dynamic_anchors_and_defs(): void
    {
        $child = new Schema(id: 'b.json', anchor: 'bAnchor');
        $defSchema = new Schema(id: 'definitions/def.json', anchor: 'defAnchor', dynamicAnchor: 'defDynamic');
        $root = new Schema(
            id: 'schemas/a.json',
            anchor: 'aAnchor',
            dynamicAnchor: 'aDynamic',
            properties: ['child' => $child],
            defs: ['Def' => $defSchema],
        );
        $document = $this->documentWithSchemas(['A' => $root], 'https://api.example.com/openapi.json');

        $registry = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame($root, $registry->findByUri('https://api.example.com/schemas/a.json'));
        self::assertSame($child, $registry->findByUri('https://api.example.com/schemas/b.json'));
        self::assertSame($defSchema, $registry->findByUri('https://api.example.com/schemas/definitions/def.json'));
        self::assertSame($root, $registry->findByAnchor('aAnchor'));
        self::assertSame($child, $registry->findByAnchor('bAnchor'));
        self::assertSame($defSchema, $registry->findByAnchor('defAnchor'));
        self::assertSame($root, $registry->findDynamicAnchor('aDynamic'));
        self::assertSame($defSchema, $registry->findDynamicAnchor('defDynamic'));
    }

    #[Test]
    public function duplicate_id_from_different_instances_throws(): void
    {
        $first = new Schema(id: 'dup.json', anchor: 'dupAnchor', title: 'first');
        $second = new Schema(id: 'dup.json', title: 'second');
        $document = $this->documentWithSchemas(['First' => $first, 'Second' => $second]);

        $this->expectException(DuplicateSchemaIdException::class);
        $this->expectExceptionMessage('dup.json');

        ResourceRegistry::forDocument($document, $this->resolver);
    }

    #[Test]
    public function id_with_non_empty_fragment_throws(): void
    {
        $schema = new Schema(id: 'https://example.com/schemas/x.json#fragment');
        $document = $this->documentWithSchemas(['A' => $schema]);

        $this->expectException(SchemaIdFragmentException::class);
        $this->expectExceptionMessage('https://example.com/schemas/x.json#fragment');

        ResourceRegistry::forDocument($document, $this->resolver);
    }

    #[Test]
    #[DataProvider('invalid_anchor_provider')]
    public function invalid_anchor_value_throws(string $keyword, string $anchor): void
    {
        $schema = 'anchor' === $keyword
            ? new Schema(anchor: $anchor)
            : new Schema(dynamicAnchor: $anchor);
        $document = $this->documentWithSchemas(['A' => $schema]);

        $this->expectException(InvalidAnchorException::class);
        $this->expectExceptionMessage($anchor);

        ResourceRegistry::forDocument($document, $this->resolver);
    }

    public static function invalid_anchor_provider(): array
    {
        return [
            'anchor starting with digit' => ['anchor', '1bad'],
            'anchor starting with dash' => ['anchor', '-bad'],
            'anchor with space' => ['anchor', 'has space'],
            'dynamicAnchor starting with digit' => ['dynamicAnchor', '9dynamic'],
            'dynamicAnchor with slash' => ['dynamicAnchor', 'a/b'],
            'anchor with trailing newline (not matched by \\z)' => ['anchor', "anchor\n"],
            'dynamicAnchor with trailing newline (not matched by \\z)' => ['dynamicAnchor', "anchor\n"],
        ];
    }

    #[Test]
    public function same_instance_indexed_through_two_components_is_not_duplicate(): void
    {
        $shared = new Schema(id: 'shared.json', anchor: 'sharedAnchor');
        $document = $this->documentWithSchemas(['First' => $shared, 'Second' => $shared]);

        $registry = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame($shared, $registry->findByUri('shared.json'));
        self::assertSame($shared, $registry->findByAnchor('sharedAnchor'));
    }

    #[Test]
    public function indexes_absolute_id_when_document_has_no_base_uri(): void
    {
        $nested = new Schema(id: 'y.json');
        $rootWithNested = new Schema(
            id: 'https://example.org/schemas/x.json',
            properties: ['nested' => $nested],
        );
        $relativeRoot = new Schema(id: 'relative-only.json');
        $document = $this->documentWithSchemas([
            'Root' => $rootWithNested,
            'Relative' => $relativeRoot,
        ]);

        $registry = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame($rootWithNested, $registry->findByUri('https://example.org/schemas/x.json'));
        self::assertSame($nested, $registry->findByUri('https://example.org/schemas/y.json'));
        self::assertSame($relativeRoot, $registry->findByUri('relative-only.json'));
    }

    #[Test]
    public function find_methods_return_null_for_unknown_keys(): void
    {
        $registry = ResourceRegistry::forDocument($this->documentWithSchemas([]), $this->resolver);

        self::assertNull($registry->findByUri('https://missing.example.com/x.json'));
        self::assertNull($registry->findByAnchor('missing'));
        self::assertNull($registry->findDynamicAnchor('missing'));
    }

    #[Test]
    public function walker_not_invoked_before_registry_access_and_runs_once_per_schema(): void
    {
        $child = new Schema(id: 'b.json');
        $defSchema = new Schema(dynamicAnchor: 'defDynamic');
        $root = new Schema(
            id: 'schemas/a.json',
            properties: ['child' => $child],
            defs: ['Def' => $defSchema],
        );
        $document = $this->documentWithSchemas(['A' => $root], 'https://api.example.com/openapi.json');
        $spy = new CountingWalker();

        self::assertSame(0, $spy->traversals);

        $registry = ResourceRegistry::build($document, $this->resolver, $spy);

        self::assertSame(3, $spy->traversals);
        self::assertSame($root, $registry->findByUri('https://api.example.com/schemas/a.json'));
        self::assertSame(3, $spy->traversals);

        ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame(3, $spy->traversals);
    }

    #[Test]
    public function for_document_returns_same_registry_on_repeated_access(): void
    {
        $document = $this->documentWithSchemas(['A' => new Schema(id: 'a.json')]);

        $first = ResourceRegistry::forDocument($document, $this->resolver);
        $second = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame($first, $second);
    }

    #[Test]
    public function for_document_with_different_resolver_returns_cached_registry(): void
    {
        $schemaA = new Schema(anchor: 'shared');
        $document = $this->documentWithSchemas(['A' => $schemaA]);

        $first = ResourceRegistry::forDocument($document, new RefResolver());
        $second = ResourceRegistry::forDocument($document, new RefResolver());

        self::assertSame($first, $second);
        self::assertSame($schemaA, $second->findByAnchor('shared'));
    }

    #[Test]
    public function resolve_does_not_build_registry(): void
    {
        $schemaA = new Schema(title: 'A');
        $document = $this->documentWithSchemas(['A' => $schemaA]);
        $spy = new CountingWalker();

        self::assertSame(0, $spy->traversals);

        $resolved = $this->resolver->resolve('#/components/schemas/A', $document);

        self::assertSame($schemaA, $resolved);
        self::assertSame(0, $spy->traversals);
        self::assertFalse(isset(self::registriesMap()[$document]));

        ResourceRegistry::build($document, $this->resolver, $spy);

        self::assertGreaterThan(0, $spy->traversals);
    }

    #[Test]
    public function anchors_are_indexed_per_resource_uri(): void
    {
        $numberSchema = new Schema(id: 'child2', anchor: 'shared_anchor', type: 'number');
        $stringSchema = new Schema(anchor: 'shared_anchor', type: 'string');
        $root = new Schema(
            id: 'https://api.example.com/foobar',
            defs: [
                'A' => new Schema(id: 'child1', allOf: [$numberSchema, $stringSchema]),
            ],
        );
        $document = $this->documentWithSchemas(['A' => $root], 'https://api.example.com/openapi.json');

        $registry = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame(
            $numberSchema,
            $registry->findAnchorIn('https://api.example.com/child2', 'shared_anchor'),
        );
        self::assertSame(
            $stringSchema,
            $registry->findAnchorIn('https://api.example.com/child1', 'shared_anchor'),
        );
        self::assertNull($registry->findAnchorIn('https://api.example.com/missing', 'shared_anchor'));
        self::assertNull($registry->findDynamicAnchorIn('https://api.example.com/child1', 'shared_anchor'));
        self::assertSame([$root], $registry->roots());
    }

    #[Test]
    public function dynamic_anchors_are_indexed_per_resource_uri(): void
    {
        $dynamic = new Schema(dynamicAnchor: 'meta', id: 'meta.json');
        $root = new Schema(id: 'https://api.example.com/root.json', defs: ['M' => $dynamic]);
        $document = $this->documentWithSchemas(['A' => $root]);

        $registry = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame($dynamic, $registry->findDynamicAnchorIn('https://api.example.com/meta.json', 'meta'));
        self::assertNull($registry->findAnchorIn('https://api.example.com/meta.json', 'meta'));
    }

    #[Test]
    public function anchors_without_resource_id_fall_into_document_base_bucket(): void
    {
        $anchored = new Schema(anchor: 'loose', id: 'loose.json');
        $root = new Schema(defs: ['L' => $anchored]);
        $document = $this->documentWithSchemas(['A' => $root]);

        $registry = ResourceRegistry::forDocument($document, $this->resolver);

        self::assertSame($anchored, $registry->findAnchorIn('loose.json', 'loose'));
    }

    #[Test]
    public function two_documents_have_independent_registries(): void
    {
        $schemaA = new Schema(anchor: 'shared-name', title: 'A');
        $schemaB = new Schema(anchor: 'shared-name', title: 'B');
        $documentA = $this->documentWithSchemas(['A' => $schemaA]);
        $documentB = $this->documentWithSchemas(['B' => $schemaB]);

        $registryA = ResourceRegistry::forDocument($documentA, $this->resolver);
        $registryB = ResourceRegistry::forDocument($documentB, $this->resolver);

        self::assertNotSame($registryA, $registryB);
        self::assertSame($schemaA, $registryA->findByAnchor('shared-name'));
        self::assertSame($schemaB, $registryB->findByAnchor('shared-name'));
    }

    #[Test]
    public function released_document_is_not_held_by_static_cache(): void
    {
        $document = $this->documentWithSchemas(['A' => new Schema(id: 'a.json')]);

        ResourceRegistry::forDocument($document, $this->resolver);

        $map = self::registriesMap();
        self::assertCount(1, $map);

        unset($document);
        gc_collect_cycles();
        gc_collect_cycles();

        self::assertCount(0, $map);
    }

    #[Test]
    public function shared_schema_referenced_by_two_roots_is_indexed_once(): void
    {
        $shared = new Schema(anchor: 'shared');
        $first = new Schema(properties: ['a' => $shared]);
        $second = new Schema(properties: ['b' => $shared]);
        $document = $this->documentWithSchemas(['First' => $first, 'Second' => $second]);
        $spy = new CountingWalker();

        $registry = ResourceRegistry::build($document, $this->resolver, $spy);

        self::assertSame($shared, $registry->findByAnchor('shared'));
        self::assertSame(3, $spy->traversals);
    }

    private static function resetRegistriesMap(): void
    {
        $property = new ReflectionProperty(ResourceRegistry::class, 'registries');
        /** @var WeakMap<OpenApiDocument, ResourceRegistry> $map */
        $map = new WeakMap();
        $property->setValue(null, $map);
    }

    private static function registriesMap(): WeakMap
    {
        $property = new ReflectionProperty(ResourceRegistry::class, 'registries');
        $map = $property->getValue();
        self::assertInstanceOf(WeakMap::class, $map);

        return $map;
    }

    /**
     * @param array<string, Schema> $schemas
     */
    private function documentWithSchemas(array $schemas, ?string $self = null): OpenApiDocument
    {
        return new OpenApiDocument(
            '3.2.0',
            new InfoObject('Test', '1.0.0'),
            components: new Components(schemas: $schemas),
            self: $self,
        );
    }
}
