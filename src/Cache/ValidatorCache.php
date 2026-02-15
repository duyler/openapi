<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Cache;

use Duyler\OpenApi\Schema\Model\Schema;
use Psr\Cache\CacheItemPoolInterface;

use function assert;

readonly class ValidatorCache
{
    private TypedCacheDecorator $decorator;

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly int $ttl = 3600,
    ) {
        $this->decorator = new TypedCacheDecorator($pool, $ttl);
    }

    public function get(string $key): ?Schema
    {
        $value = $this->decorator->get($key, Schema::class);

        if (null === $value) {
            return null;
        }

        $schema = $value;
        assert($schema instanceof Schema);

        return $schema;
    }

    public function set(string $key, Schema $schema): void
    {
        $this->decorator->set($key, $schema);
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
