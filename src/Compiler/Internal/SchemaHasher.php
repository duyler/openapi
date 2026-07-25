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
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/** @internal */
final readonly class SchemaHasher
{
    private const string KEY_SEPARATOR = '|';

    public function __construct(
        private readonly DocumentFingerprinter $fingerprinter = new DocumentFingerprinter(),
    ) {}

    /**
     * @param WeakMap<Schema, array<string, string>>    $hashCache
     * @param WeakMap<OpenApiDocument, string>          $documentFingerprints
     */
    public function calculate(
        Schema $schema,
        string $className,
        ?OpenApiDocument $document,
        WeakMap $hashCache,
        WeakMap $documentFingerprints,
    ): string {
        $classNameHash = hash('sha256', $className);
        $documentFingerprint = null !== $document
            ? $this->fingerprinter->fingerprint($document, $documentFingerprints)
            : '';
        $cacheKey = $classNameHash . self::KEY_SEPARATOR . $documentFingerprint;

        if ($hashCache->offsetExists($schema)) {
            /** @var array<string, string> $entry */
            $entry = $hashCache[$schema];
            if (isset($entry[$cacheKey])) {
                /** @var string */
                return $entry[$cacheKey];
            }
        }

        $this->assertNoUnresolvedRefs($schema, $document);
        $schemaHash = $this->hashSchema($schema, $document);
        $finalHash = $this->combineHashes($classNameHash, $schemaHash, $documentFingerprint);
        $this->storeHash($hashCache, $schema, $cacheKey, $finalHash);

        return $finalHash;
    }

    private function assertNoUnresolvedRefs(Schema $schema, ?OpenApiDocument $document): void
    {
        $resolvedSchema = null !== $document
            ? $this->fingerprinter->resolveRefsForHash($schema, $document, [])
            : $schema;

        if (null !== $resolvedSchema->ref) {
            throw new CompilationCacheException(
                'Schema contains $ref but no document context provided; cannot generate stable cache key',
            );
        }

        if (null === $document && $this->fingerprinter->schemaContainsRef($schema, [])) {
            throw new CompilationCacheException(
                'Schema contains $ref but no document context provided; cannot generate stable cache key',
            );
        }
    }

    private function hashSchema(Schema $schema, ?OpenApiDocument $document): string
    {
        $resolvedSchema = null !== $document
            ? $this->fingerprinter->resolveRefsForHash($schema, $document, [])
            : $schema;

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

        return hash('sha256', $json);
    }

    private function combineHashes(string $classNameHash, string $schemaHash, string $documentFingerprint): string
    {
        $compound = $classNameHash
            . self::KEY_SEPARATOR
            . $schemaHash
            . self::KEY_SEPARATOR
            . $documentFingerprint;

        return hash('sha256', $compound);
    }

    /**
     * @param WeakMap<Schema, array<string, string>> $hashCache
     */
    private function storeHash(WeakMap $hashCache, Schema $schema, string $cacheKey, string $finalHash): void
    {
        if (! $hashCache->offsetExists($schema)) {
            /** @var array<string, string> */
            $hashCache[$schema] = [];
        }

        /** @var array<string, string> $entry */
        $entry = $hashCache[$schema];
        $entry[$cacheKey] = $finalHash;
        /** @var array<string, string> */
        $hashCache[$schema] = $entry;
    }
}
