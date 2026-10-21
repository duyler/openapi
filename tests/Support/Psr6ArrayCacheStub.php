<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Builder\Internal\CacheKeyBuilder;
use Duyler\OpenApi\Builder\Internal\ExternalRefDetector;
use Override;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function count;
use function str_ends_with;
use function str_starts_with;

final class Psr6ArrayCacheStub implements CacheItemPoolInterface
{
    public int $readCount = 0;

    public int $writeCount = 0;

    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, bool> */
    private array $savedFlags = [];

    /** @var list<string> */
    private array $requestedKeys = [];

    /** @var list<string> */
    private array $savedKeys = [];

    private int $missCount = 0;

    #[Override]
    public function getItem(string $key): CacheItemInterface
    {
        ++$this->readCount;
        $this->requestedKeys[] = $key;

        if (array_key_exists($key, $this->values)) {
            return new Psr6ArrayCacheItem($key, $this->values[$key], $this->savedFlags[$key] ?? false);
        }

        if (false === str_ends_with($key, ExternalRefDetector::CONFINEMENT_KEY_SUFFIX)) {
            ++$this->missCount;
            $this->values[$key] = null;
        }

        return new Psr6ArrayCacheItem($key, null, false);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, CacheItemInterface>
     */
    #[Override]
    public function getItems(array $keys = []): array
    {
        $items = [];

        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    #[Override]
    public function hasItem(string $key): bool
    {
        return ($this->savedFlags[$key] ?? false) && array_key_exists($key, $this->values);
    }

    #[Override]
    public function clear(): bool
    {
        $this->values = [];
        $this->savedFlags = [];
        $this->requestedKeys = [];
        $this->savedKeys = [];
        $this->missCount = 0;
        $this->readCount = 0;
        $this->writeCount = 0;

        return true;
    }

    #[Override]
    public function deleteItem(string $key): bool
    {
        unset($this->values[$key], $this->savedFlags[$key]);

        return true;
    }

    /**
     * @param list<string> $keys
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->values[$key], $this->savedFlags[$key]);
        }

        return true;
    }

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        $key = $item->getKey();
        ++$this->writeCount;
        $this->values[$key] = $item->get();
        $this->savedFlags[$key] = true;

        if (false === str_ends_with($key, ExternalRefDetector::CONFINEMENT_KEY_SUFFIX)) {
            $this->savedKeys[] = $key;
        }

        return true;
    }

    #[Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    #[Override]
    public function commit(): bool
    {
        return true;
    }

    public function missCount(): int
    {
        return $this->missCount;
    }

    public function saveCount(): int
    {
        return count(array_unique($this->savedKeys));
    }

    public function requestedCount(): int
    {
        return count($this->requestedKeys);
    }

    /** @return list<string> */
    public function capturedKeys(): array
    {
        return $this->requestedKeys;
    }

    /** @return list<string> */
    public function contentKeys(): array
    {
        $unique = [];

        foreach ($this->requestedKeys as $key) {
            if (str_starts_with($key, CacheKeyBuilder::CONTENT_PREFIX) && false === str_ends_with($key, ExternalRefDetector::CONFINEMENT_KEY_SUFFIX)) {
                $unique[$key] = true;
            }
        }

        return array_keys($unique);
    }

    /** @return list<string> */
    public function confinementKeys(): array
    {
        $keys = [];

        foreach (array_keys($this->values) as $key) {
            if (str_ends_with($key, ExternalRefDetector::CONFINEMENT_KEY_SUFFIX)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function value(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }
}
