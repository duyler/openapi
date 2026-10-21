<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Psr\Cache\CacheItemPoolInterface;

use function assert;

final readonly class SchemaCache
{
    private TypedCacheDecorator $decorator;

    /**
     * Create a new schema cache.
     *
     * @param CacheItemPoolInterface $pool PSR-6 cache pool
     * @param int $ttl Time to live in seconds (default: TypedCacheDecorator::DEFAULT_TTL)
     */
    public function __construct(
        CacheItemPoolInterface $pool,
        int $ttl = TypedCacheDecorator::DEFAULT_TTL,
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

        assert(null === $value || $value instanceof OpenApiDocument);

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

    /**
     * Retrieve cached confinement verdict.
     *
     * @param string $key Cache key
     * @return ConfinementVerdict|null Cached verdict or null if not found
     */
    public function getConfinement(string $key): ?ConfinementVerdict
    {
        $value = $this->decorator->get($key, ConfinementVerdict::class);

        assert(null === $value || $value instanceof ConfinementVerdict);

        return $value;
    }

    /**
     * Store the clean confinement verdict under the given cache key.
     *
     * @param string $key Cache key
     */
    public function setConfinement(string $key): void
    {
        $this->decorator->set($key, ConfinementVerdict::Clean);
    }
}
