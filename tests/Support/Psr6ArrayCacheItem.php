<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Override;
use Psr\Cache\CacheItemInterface;

use DateInterval;
use DateTimeInterface;

final class Psr6ArrayCacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value,
        private readonly bool $hit,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return $this->key;
    }

    #[Override]
    public function get(): mixed
    {
        return $this->value;
    }

    #[Override]
    public function isHit(): bool
    {
        return $this->hit;
    }

    #[Override]
    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    #[Override]
    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    #[Override]
    public function expiresAfter(int|DateInterval|null $time): static
    {
        return $this;
    }
}
