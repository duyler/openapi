<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use InvalidArgumentException;

use function sprintf;
use function count;

final class ValidatorPool
{
    private const int DEFAULT_MAX_SIZE = 128;

    /** @var array<string, object> */
    private array $cache = [];

    /** @var array<string, true> */
    private array $order = [];

    private readonly int $maxSize;

    public function __construct(?int $maxSize = null)
    {
        $resolvedMaxSize = $maxSize ?? self::DEFAULT_MAX_SIZE;

        if (1 > $resolvedMaxSize) {
            throw new InvalidArgumentException(
                sprintf('Max size must be at least 1, got %d', $resolvedMaxSize),
            );
        }

        $this->maxSize = $resolvedMaxSize;
    }

    /**
     * @template T of object
     * @param callable(): T $factory
     * @return T
     */
    public function getOrCreate(string $key, callable $factory): object
    {
        if (isset($this->cache[$key])) {
            $this->touch($key);

            /** @var T */
            return $this->cache[$key];
        }

        $instance = $factory();
        $this->cache[$key] = $instance;
        $this->order[$key] = true;

        if (count($this->cache) > $this->maxSize) {
            $evictedKey = array_key_first($this->order);
            unset($this->order[$evictedKey], $this->cache[$evictedKey]);
        }

        return $instance;
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->order = [];
    }

    private function touch(string $key): void
    {
        unset($this->order[$key]);
        $this->order[$key] = true;
    }
}
