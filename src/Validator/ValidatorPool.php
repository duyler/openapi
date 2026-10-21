<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Validator\Internal\LruCache;
use InvalidArgumentException;

use function assert;
use function method_exists;

final readonly class ValidatorPool
{
    private const int DEFAULT_MAX_SIZE = 128;

    /** @var LruCache<string, object> */
    private LruCache $cache;

    /**
     * @param int|null $maxSize
     * @param object|null $lock
     */
    public function __construct(?int $maxSize = null, private ?object $lock = null)
    {
        /** @var LruCache<string, object> */
        $this->cache = new LruCache($maxSize ?? self::DEFAULT_MAX_SIZE);

        if (null !== $lock && (false === method_exists($lock, 'lock') || false === method_exists($lock, 'unlock'))) {
            throw new InvalidArgumentException(
                'Lock object must expose both lock() and unlock() methods',
            );
        }
    }

    public static function forCoroutineRuntime(object $lock, ?int $maxSize = null): self
    {
        return new self($maxSize, $lock);
    }

    /**
     * @template T of object
     * @param callable(): T $factory
     * @return T
     */
    public function getOrCreate(string $key, callable $factory): object
    {
        $this->acquireLock();
        try {
            $cachedValidator = $this->cache->get($key);

            if (null !== $cachedValidator) {
                /** @var T */
                return $cachedValidator;
            }

            $instance = $factory();
            $this->cache->set($key, $instance);

            return $instance;
        } finally {
            $this->releaseLock();
        }
    }

    public function clear(): void
    {
        $this->acquireLock();
        try {
            $this->cache->clear();
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): void
    {
        if (null === $this->lock) {
            return;
        }

        assert(method_exists($this->lock, 'lock'));
        $this->lock->lock();
    }

    private function releaseLock(): void
    {
        if (null === $this->lock) {
            return;
        }

        assert(method_exists($this->lock, 'unlock'));
        $this->lock->unlock();
    }
}
