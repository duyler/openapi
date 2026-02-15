<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Psr\Cache\CacheItemPoolInterface;

use function assert;

/**
 * PSR-6 cache wrapper for OpenAPI documents.
 *
 * Provides caching for parsed OpenAPI specifications to improve performance
 * by avoiding repeated parsing of the same documents.
 */
readonly class SchemaCache
{
    private TypedCacheDecorator $decorator;

    /**
     * Create a new schema cache.
     *
     * @param CacheItemPoolInterface $pool PSR-6 cache pool
     * @param int $ttl Time to live in seconds (default: 3600)
     *
     * @example
     * $cache = new SchemaCache($symfonyCacheAdapter, 7200);
     */
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $ttl = 3600,
    ) {
        $this->decorator = new TypedCacheDecorator($pool, $ttl);
    }

    /**
     * Retrieve cached OpenAPI document.
     *
     * @param string $key Cache key
     * @return OpenApiDocument|null Cached document or null if not found
     */
    public function get(string $key): ?OpenApiDocument
    {
        $value = $this->decorator->get($key, OpenApiDocument::class);

        if (null === $value) {
            return null;
        }

        $document = $value;
        assert($document instanceof OpenApiDocument);

        return $document;
    }

    public function set(string $key, OpenApiDocument $document): void
    {
        $this->decorator->set($key, $document);
    }

    public function delete(string $key): void
    {
        $this->decorator->delete($key);
    }

    public function clear(): void
    {
        $this->decorator->clear();
    }

    public function has(string $key): bool
    {
        return $this->decorator->has($key);
    }
}
