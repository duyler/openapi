<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Duyler\OpenApi\Schema\Model\Schema;
use Psr\Cache\CacheItemPoolInterface;

readonly class ValidatorCache
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $ttl = 3600,
    ) {}

    public function get(string $key): ?Schema
    {
        $item = $this->pool->getItem($key);

        if (false === $item->isHit()) {
            return null;
        }

        $schema = $item->get();

        if (false === $schema instanceof Schema) {
            return null;
        }

        return $schema;
    }

    public function set(string $key, Schema $schema): void
    {
        $item = $this->pool->getItem($key);
        $item->set($schema);
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
