<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Internal\DocumentFingerprinter;
use Duyler\OpenApi\Compiler\Internal\SchemaHasher;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use InvalidArgumentException;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use WeakMap;

use function is_string;
use function sprintf;

final readonly class CompilationCache implements CompilationCacheInterface
{
    public const int DEFAULT_TTL = 86400;

    /** @var WeakMap<Schema, array<string, string>> */
    private WeakMap $hashCache;

    /** @var WeakMap<OpenApiDocument, string> */
    private WeakMap $documentFingerprints;

    private SchemaHasher $hasher;

    public function __construct(
        private CacheItemPoolInterface $pool,
        private string $namespace = 'validator_compilation',
        private int $ttl = self::DEFAULT_TTL,
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

        $this->hasher = new SchemaHasher(new DocumentFingerprinter());
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
        $hash = $this->hasher->calculate(
            $schema,
            $className,
            $document,
            $this->hashCache,
            $this->documentFingerprints,
        );

        return $this->namespace . '.' . $hash;
    }
}
