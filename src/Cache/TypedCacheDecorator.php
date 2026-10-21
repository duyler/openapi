<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

use function sprintf;

final readonly class TypedCacheDecorator
{
    public const int DEFAULT_TTL = 3600;

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $ttl = self::DEFAULT_TTL,
    ) {
        self::validateTtl($ttl);
    }

    public static function validateTtl(int $ttl): void
    {
        if (1 > $ttl) {
            throw new InvalidArgumentException(
                sprintf('TTL must be a positive integer, got %d.', $ttl),
            );
        }
    }

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

        /** @var object|null $value */
        $value = $item->get();

        if (null === $value) {
            return null;
        }

        if (false === $value instanceof $expectedType) {
            return null;
        }

        return $value;
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
