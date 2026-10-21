<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use InvalidArgumentException;

use function array_key_exists;
use function array_key_first;
use function assert;
use function count;
use function sprintf;

/**
 * @internal
 * @template K of string
 * @template V
 */
final class LruCache
{
    /** @var array<K, V> */
    private array $items = [];

    /** @var array<K, true> */
    private array $order = [];

    public function __construct(private readonly int $maxSize)
    {
        if (1 > $maxSize) {
            throw new InvalidArgumentException(
                sprintf('Max size must be at least 1, got %d', $maxSize),
            );
        }
    }

    /**
     * @param K $key
     * @return V|null
     */
    public function get(string $key): mixed
    {
        if (false === array_key_exists($key, $this->items)) {
            return null;
        }

        $this->touch($key);

        return $this->items[$key];
    }

    /**
     * @param K $key
     * @param V $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
        $this->touch($key);

        if (count($this->items) > $this->maxSize) {
            $this->evict();
        }
    }

    /**
     * @param K $key
     */
    public function touch(string $key): void
    {
        if (false === array_key_exists($key, $this->items)) {
            return;
        }

        unset($this->order[$key]);
        $this->order[$key] = true;
    }

    public function clear(): void
    {
        $this->items = [];
        $this->order = [];
    }

    private function evict(): void
    {
        $evictedKey = array_key_first($this->order);

        assert(null !== $evictedKey);

        unset($this->order[$evictedKey], $this->items[$evictedKey]);
    }
}
