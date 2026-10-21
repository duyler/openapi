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
     * @param CacheItemPoolInterface $pool
     * @param int $ttl
     */
    public function __construct(
        CacheItemPoolInterface $pool,
        int $ttl = TypedCacheDecorator::DEFAULT_TTL,
    ) {
        $this->decorator = new TypedCacheDecorator($pool, $ttl);
    }

    /**
     * @param string $key
     * @return OpenApiDocument|null
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
     * @param string $key
     * @return ConfinementVerdict|null
     */
    public function getConfinement(string $key): ?ConfinementVerdict
    {
        $value = $this->decorator->get($key, ConfinementVerdict::class);

        assert(null === $value || $value instanceof ConfinementVerdict);

        return $value;
    }

    /**
     * @param string $key
     */
    public function setConfinement(string $key): void
    {
        $this->decorator->set($key, ConfinementVerdict::Clean);
    }
}
