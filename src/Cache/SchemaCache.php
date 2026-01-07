<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Psr\Cache\CacheItemPoolInterface;

/**
 * PSR-6 cache wrapper for OpenAPI documents.
 *
 * Provides caching for parsed OpenAPI specifications to improve performance
 * by avoiding repeated parsing of the same documents.
 */
readonly class SchemaCache
{
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
    ) {}

    /**
     * Retrieve cached OpenAPI document.
     *
     * @param string $key Cache key
     * @return OpenApiDocument|null Cached document or null if not found
     */
    public function get(string $key): ?OpenApiDocument
    {
        $item = $this->pool->getItem($key);

        if (false === $item->isHit()) {
            return null;
        }

        $document = $item->get();

        if (false === $document instanceof OpenApiDocument) {
            return null;
        }

        return $document;
    }

    public function set(string $key, OpenApiDocument $document): void
    {
        $item = $this->pool->getItem($key);
        $item->set($document);
        $item->expiresAfter($this->ttl);

        $this->pool->save($item);
    }

    public function delete(string $key): void
    {
        $this->pool->deleteItem($key);
    }

    public function clear(): void
    {
        $this->pool->clear();
    }

    public function has(string $key): bool
    {
        return $this->pool->hasItem($key);
    }
}
