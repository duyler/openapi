<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Compiler\Exception\CompilationCacheException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use JsonException;
use WeakMap;

use function hash;
use function in_array;
use function json_encode;
use function sprintf;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/** @internal */
final readonly class DocumentFingerprinter
{
    private const int REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH = 21;

    /**
     * @param WeakMap<OpenApiDocument, string> $fingerprints
     */
    public function fingerprint(OpenApiDocument $document, WeakMap $fingerprints): string
    {
        if ($fingerprints->offsetExists($document)) {
            /** @var string */
            return $fingerprints[$document];
        }

        /** @var WeakMap<Schema, int> $visited */
        $visited = new WeakMap();
        $converter = new SchemaToArrayConverter();
        $schemas = $document->components?->schemas ?? [];

        $snapshots = [];
        foreach ($schemas as $name => $schema) {
            $snapshots[$name] = $converter->toSnapshotArray($schema, $visited);
        }

        try {
            $json = json_encode($snapshots, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CompilationCacheException(
                sprintf('Failed to encode document components for fingerprint: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $fingerprint = hash('sha256', $json);
        $fingerprints[$document] = $fingerprint;

        return $fingerprint;
    }

    /** @param list<string> $visited */
    public function resolveRefsForHash(Schema $schema, OpenApiDocument $document, array $visited): Schema
    {
        if (null !== $schema->ref) {
            if (in_array($schema->ref, $visited, true)) {
                throw new CompilationCacheException(
                    sprintf('Circular $ref detected while calculating cache key: %s', $schema->ref),
                );
            }

            $visited[] = $schema->ref;
            $resolved = $this->resolveComponentRef($schema->ref, $document);

            return $this->resolveRefsForHash($resolved, $document, $visited);
        }

        $resolvedProperties = null;
        if (null !== $schema->properties) {
            $resolvedProperties = [];
            foreach ($schema->properties as $name => $property) {
                $resolvedProperties[$name] = $this->resolveRefsForHash($property, $document, $visited);
            }
        }

        $resolvedItems = $schema->items instanceof Schema
            ? $this->resolveRefsForHash($schema->items, $document, $visited)
            : null;

        return $schema->withOverrides(
            properties: $resolvedProperties,
            items: $resolvedItems,
        );
    }

    public function resolveComponentRef(string $ref, OpenApiDocument $document): Schema
    {
        if (false === str_starts_with($ref, '#/components/schemas/')) {
            throw new CompilationCacheException(sprintf('Unsupported $ref for cache key: %s', $ref));
        }

        $schemaName = substr($ref, self::REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH);
        $schemas = $document->components?->schemas ?? [];

        if (false === isset($schemas[$schemaName])) {
            throw new CompilationCacheException(sprintf('Schema not found: %s', $schemaName));
        }

        return $schemas[$schemaName];
    }

    /** @param list<Schema> $visited */
    public function schemaContainsRef(Schema $schema, array $visited): bool
    {
        if (in_array($schema, $visited, true)) {
            return false;
        }

        $visited[] = $schema;

        if (null !== $schema->ref) {
            return true;
        }

        if (null !== $schema->properties) {
            foreach ($schema->properties as $property) {
                if ($this->schemaContainsRef($property, $visited)) {
                    return true;
                }
            }
        }

        if ($schema->items instanceof Schema && $this->schemaContainsRef($schema->items, $visited)) {
            return true;
        }

        return false;
    }
}
