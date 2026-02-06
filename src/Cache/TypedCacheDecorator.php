<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

final readonly class TypedCacheDecorator
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $ttl = 3600,
    ) {}

    /**
     * @param class-string $expectedType
     * @return ?object
     */
    public function get(string $key, string $expectedType): ?object
    {
        $item = $this->pool->getItem($key);

        if (false === $item->isHit()) {
            return null;
        }

        $value = $item->get();

        if (null === $value) {
            return null;
        }

        if (false === class_exists($expectedType)) {
            throw new RuntimeException("Expected type class does not exist: {$expectedType}");
        }

        if (false === $value instanceof $expectedType) {
            return null;
        }

        /** @var object */
        $result = $value;

        return $result;
    }

    public function set(string $key, object $value): void
    {
        $item = $this->pool->getItem($key);
        $item->set($value);
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
