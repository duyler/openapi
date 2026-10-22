<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use WeakMap;

/** @internal */
final class ResourceRegistry
{
    /** @var WeakMap<OpenApiDocument, ResourceRegistry>|null */
    private static ?WeakMap $registries = null;

    /** @var array<string, Schema> */
    private array $byUri = [];

    /** @var array<string, Schema> */
    private array $byAnchor = [];

    /** @var array<string, Schema> */
    private array $byDynamicAnchor = [];

    /** @var list<Schema> */
    private array $roots = [];

    /** @var array<string, array<string, Schema>> */
    private array $byAnchorInResource = [];

    /** @var array<string, array<string, Schema>> */
    private array $byDynamicAnchorInResource = [];

    private function __construct(
        private readonly SchemaSubschemaWalkerInterface $walker,
    ) {}

    public static function forDocument(OpenApiDocument $document, RefResolverInterface $refResolver): self
    {
        $registries = self::$registries;

        if (null === $registries) {
            /** @var WeakMap<OpenApiDocument, ResourceRegistry> $registries */
            $registries = new WeakMap();
            self::$registries = $registries;
        }

        $registry = $registries[$document] ?? null;
        if ($registry instanceof ResourceRegistry) {
            return $registry;
        }

        $registry = self::build($document, $refResolver, new SchemaSubschemaWalker());
        $registries[$document] = $registry;

        return $registry;
    }

    public static function build(
        OpenApiDocument $document,
        RefResolverInterface $refResolver,
        SchemaSubschemaWalkerInterface $walker,
    ): self {
        $registry = new self($walker);
        $registry->indexDocument($document, $refResolver);

        return $registry;
    }

    public function findByUri(string $uri): ?Schema
    {
        return $this->byUri[$uri] ?? null;
    }

    public function findByAnchor(string $name): ?Schema
    {
        return $this->byAnchor[$name] ?? null;
    }

    public function findDynamicAnchor(string $name): ?Schema
    {
        return $this->byDynamicAnchor[$name] ?? null;
    }

    /** @return list<Schema> */
    public function roots(): array
    {
        return $this->roots;
    }

    public function findAnchorIn(string $resourceUri, string $name): ?Schema
    {
        return $this->byAnchorInResource[$resourceUri][$name] ?? null;
    }

    public function findDynamicAnchorIn(string $resourceUri, string $name): ?Schema
    {
        return $this->byDynamicAnchorInResource[$resourceUri][$name] ?? null;
    }

    private function indexDocument(OpenApiDocument $document, RefResolverInterface $refResolver): void
    {
        $baseUri = $refResolver->getBaseUri($document);
        $roots = $document->components?->schemas ?? [];

        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();

        foreach ($roots as $rootSchema) {
            $this->roots[] = $rootSchema;
            $this->indexSchema($rootSchema, $baseUri, $refResolver, $visited);
        }
    }

    /**
     * @param WeakMap<Schema, true> $visited
     */
    private function indexSchema(
        Schema $schema,
        ?string $baseUri,
        RefResolverInterface $refResolver,
        WeakMap $visited,
    ): void {
        if (isset($visited[$schema])) {
            return;
        }

        $visited[$schema] = true;

        if (null !== $schema->id) {
            $absoluteUri = null === $baseUri ? $schema->id : $refResolver->combineUris($baseUri, $schema->id);
            $this->byUri[$absoluteUri] ??= $schema;
            $baseUri = $absoluteUri;
        }

        if (null !== $schema->anchor) {
            $this->byAnchor[$schema->anchor] ??= $schema;
            $this->byAnchorInResource[$baseUri ?? ''][$schema->anchor] ??= $schema;
        }

        if (null !== $schema->dynamicAnchor) {
            $this->byDynamicAnchor[$schema->dynamicAnchor] ??= $schema;
            $this->byDynamicAnchorInResource[$baseUri ?? ''][$schema->dynamicAnchor] ??= $schema;
        }

        foreach ($this->walker->iterateSubSchemas($schema) as $subSchema) {
            $this->indexSchema($subSchema, $baseUri, $refResolver, $visited);
        }
    }
}
