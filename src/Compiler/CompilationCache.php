<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\CompilationCacheException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use WeakMap;

use function hash;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class CompilationCache implements CompilationCacheInterface
{
    public const int DEFAULT_TTL = 86400;

    private const string KEY_SEPARATOR = '|';

    private const int REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH = 21;

    /** @var WeakMap<Schema, array<string, string>> */
    private WeakMap $hashCache;

    /** @var WeakMap<OpenApiDocument, string> */
    private WeakMap $documentFingerprints;

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly string $namespace = 'validator_compilation',
        private readonly int $ttl = self::DEFAULT_TTL,
    ) {
        if ($ttl < 1) {
            throw new InvalidArgumentException(
                sprintf('TTL must be a positive integer, got %d.', $ttl),
            );
        }

        /** @var WeakMap<Schema, array<string, string>> */
        $this->hashCache = new WeakMap();

        /** @var WeakMap<OpenApiDocument, string> */
        $this->documentFingerprints = new WeakMap();
    }

    #[Override]
    public function get(string $schemaHash): ?string
    {
        $item = $this->pool->getItem($schemaHash);

        if (false === $item->isHit()) {
            return null;
        }

        $code = $item->get();

        if (false === is_string($code)) {
            return null;
        }

        return $code;
    }

    #[Override]
    public function set(string $schemaHash, string $compiledCode): void
    {
        $item = $this->pool->getItem($schemaHash);
        $item->set($compiledCode);
        $item->expiresAfter($this->ttl);

        $this->pool->save($item);
    }

    /**
     * Generates a deterministic PSR-6 cache key for a compiled validator.
     *
     * The key incorporates three independent inputs so that no two distinct
     * (schema, class name, document context) triples can collide:
     *   - the SHA-256 hash of the class name, so the same schema compiled
     *     under different class names always produces different keys and
     *     never reuses a stale cached class with the wrong short name;
     *   - the SHA-256 hash of the schema snapshot, resolved against the
     *     document when one is supplied so `$ref` targets are inlined
     *     instead of hashed as opaque pointers;
     *   - the SHA-256 fingerprint of the document's `components.schemas`
     *     map, so two documents that expose the same `$ref` pointer but
     *     resolve it to different target schemas cannot share a key
     *     (cross-document cache poisoning defence).
     *
     * The compound input is hashed through SHA-256 once more so the
     * returned key never exceeds `namespace.length + 1 + 64` characters,
     * staying inside PSR-6 pool length and charset limits regardless of
     * how long the class name is.
     *
     * Behaviour change: a schema that still contains a `$ref` after the
     * optional document resolution throws a `CompilationCacheException`.
     * Previously the literal `$ref` pointer was hashed as-is, silently
     * colliding across documents that resolved the same pointer to
     * different targets; fail-closed is safer than silent collision.
     *
     * @param ?OpenApiDocument $document Optional document used to resolve
     *        `#/components/schemas/...` pointers before hashing. Required
     *        (non-null) when the schema contains any `$ref`.
     */
    #[Override]
    public function generateKey(Schema $schema, string $className, ?OpenApiDocument $document = null): string
    {
        $hash = $this->calculateSchemaHash($schema, $className, $document);

        return $this->namespace . '.' . $hash;
    }

    private function calculateSchemaHash(Schema $schema, string $className, ?OpenApiDocument $document): string
    {
        $classNameHash = $this->hashClassName($className);
        $documentFingerprint = $document !== null ? $this->documentFingerprint($document) : '';
        $cacheKey = $classNameHash . self::KEY_SEPARATOR . $documentFingerprint;

        if ($this->hashCache->offsetExists($schema)) {
            /** @var array<string, string> $entry */
            $entry = $this->hashCache[$schema];
            if (isset($entry[$cacheKey])) {
                /** @var string */
                return $entry[$cacheKey];
            }
        }

        $resolvedSchema = $document !== null
            ? $this->resolveRefsForHash($schema, $document, [])
            : $schema;

        if (null !== $resolvedSchema->ref) {
            throw new CompilationCacheException(
                'Schema contains $ref but no document context provided; cannot generate stable cache key',
            );
        }

        if (null === $document && $this->schemaContainsRef($schema, [])) {
            throw new CompilationCacheException(
                'Schema contains $ref but no document context provided; cannot generate stable cache key',
            );
        }

        /** @var WeakMap<Schema, int> $visited */
        $visited = new WeakMap();
        $data = new SchemaToArrayConverter()->toSnapshotArray($resolvedSchema, $visited);

        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CompilationCacheException(
                sprintf('Failed to encode schema for hash: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $schemaHash = hash('sha256', $json);
        $compound = $classNameHash . self::KEY_SEPARATOR . $schemaHash . self::KEY_SEPARATOR . $documentFingerprint;
        $finalHash = hash('sha256', $compound);

        if (! $this->hashCache->offsetExists($schema)) {
            /** @var array<string, string> */
            $this->hashCache[$schema] = [];
        }

        /** @var array<string, string> $entry */
        $entry = $this->hashCache[$schema];
        $entry[$cacheKey] = $finalHash;
        /** @var array<string, string> */
        $this->hashCache[$schema] = $entry;

        return $finalHash;
    }

    private function hashClassName(string $className): string
    {
        return hash('sha256', $className);
    }

    /**
     * Memoized per-process fingerprint of an OpenApiDocument's resolvable
     * schema content. Only `components.schemas` are hashed because external
     * `$ref` targets are out of scope for the compiler.
     */
    private function documentFingerprint(OpenApiDocument $document): string
    {
        if ($this->documentFingerprints->offsetExists($document)) {
            /** @var string */
            return $this->documentFingerprints[$document];
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

        $this->documentFingerprints[$document] = $fingerprint;

        return $fingerprint;
    }

    /**
     * In-memory resolution of `#/components/schemas/...` pointers for
     * cache-key hashing only. Mirrors `ValidatorCompiler::resolveRefs`
     * cycle-detection so circular `$ref` chains cannot cause a stack
     * overflow during key generation.
     *
     * @param list<string> $visited
     */
    private function resolveRefsForHash(Schema $schema, OpenApiDocument $document, array $visited): Schema
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

        $resolvedItems = $schema->items !== null
            ? $this->resolveRefsForHash($schema->items, $document, $visited)
            : null;

        return $schema->withOverrides(
            properties: $resolvedProperties,
            items: $resolvedItems,
        );
    }

    private function resolveComponentRef(string $ref, OpenApiDocument $document): Schema
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

    /**
     * Walks the schema tree (properties and items) to determine whether
     * any node carries a `$ref`. Used to fail-closed when no document
     * context is supplied: a schema whose top-level shape is `$ref`-free
     * but whose nested properties or items reference another component
     * would otherwise be hashed with the literal `$ref` pointer string,
     * silently colliding across documents that resolve the pointer to
     * different targets.
     *
     * Cycle detection uses a list of visited Schema objects compared
     * by identity (`in_array` with strict=true), so hand-constructed
     * cyclic Schema graphs cannot trigger infinite recursion. This is
     * distinct from the `$visited` list of ref-strings used in
     * `resolveRefsForHash`, which guards cycle detection during
     * in-memory resolution against repeated ref-string targets.
     *
     * @param list<Schema> $visited
     */
    private function schemaContainsRef(Schema $schema, array $visited): bool
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

        if (null !== $schema->items && $this->schemaContainsRef($schema->items, $visited)) {
            return true;
        }

        return false;
    }
}
