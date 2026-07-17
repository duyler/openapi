<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Psr\Cache\CacheItemPoolInterface;

use RuntimeException;

use function sprintf;

final readonly class SchemaCache
{
    private const int DEFAULT_TTL = 3600;

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
        private readonly int $ttl = self::DEFAULT_TTL,
    ) {
        $this->decorator = new TypedCacheDecorator($pool, $this->ttl);
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

        if (false === $value instanceof OpenApiDocument) {
            throw new RuntimeException(sprintf(
                'Cached value for key "%s" is not an OpenApiDocument instance',
                $key,
            ));
        }

        return $value;
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
