<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use InvalidArgumentException;

use function count;
use function method_exists;
use function sprintf;

/**
 * Thread-safety: NOT thread-safe by default.
 *
 * Safe to share across requests in prefork models (PHP-FPM, RoadRunner,
 * FrankenPHP non-threaded) where each worker keeps isolated state.
 *
 * In Swoole with coroutines or FrankenPHP with threaded workers, concurrent
 * getOrCreate() calls race on the check-then-act sequence and may both invoke
 * the factory for the same key. Inject a lock object (e.g. Swoole\Lock, or any
 * object exposing lock()/unlock() methods) via the constructor to serialize
 * access. Without a lock the pool stays prefork-safe but racy under shared
 * state.
 *
 * The $factory passed to getOrCreate() must be non-blocking (no I/O) and
 * non-recursive (no nested getOrCreate() calls); otherwise the pool deadlocks
 * while the lock is held.
 */
final class ValidatorPool
{
    private const int DEFAULT_MAX_SIZE = 128;

    /** @var array<string, object> */
    private array $cache = [];

    /** @var array<string, true> */
    private array $order = [];

    private readonly int $maxSize;

    /**
     * @param int|null    $maxSize Maximum entries before LRU eviction. Null falls back to DEFAULT_MAX_SIZE.
     * @param object|null $lock    Optional lock with lock()/unlock() methods (e.g. Swoole\Lock). Null keeps
     *                             the pool prefork-safe; required for Swoole coroutines and threaded FrankenPHP.
     *                             A non-null object must expose BOTH methods; otherwise the constructor throws.
     */
    public function __construct(?int $maxSize = null, private readonly ?object $lock = null)
    {
        $resolvedMaxSize = $maxSize ?? self::DEFAULT_MAX_SIZE;

        if (1 > $resolvedMaxSize) {
            throw new InvalidArgumentException(
                sprintf('Max size must be at least 1, got %d', $resolvedMaxSize),
            );
        }

        if (null !== $lock && (!method_exists($lock, 'lock') || !method_exists($lock, 'unlock'))) {
            throw new InvalidArgumentException(
                'Lock object must expose both lock() and unlock() methods',
            );
        }

        $this->maxSize = $resolvedMaxSize;
    }

    /**
     * @template T of object
     *
     * @param callable(): T $factory Must be non-blocking (no I/O) and non-recursive
     *     (no nested getOrCreate() calls). When a lock is configured, the lock is
     *     held for the entire duration of $factory; suspending inside $factory
     *     (e.g. Swoole coroutine I/O) or calling getOrCreate() recursively on the
     *     same key will deadlock.
     *
     * @return T
     */
    public function getOrCreate(string $key, callable $factory): object
    {
        $this->acquireLock();
        try {
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
        } finally {
            $this->releaseLock();
        }
    }

    public function clear(): void
    {
        $this->acquireLock();
        try {
            $this->cache = [];
            $this->order = [];
        } finally {
            $this->releaseLock();
        }
    }

    private function acquireLock(): void
    {
        if (null !== $this->lock && method_exists($this->lock, 'lock')) {
            $this->lock->lock();
        }
    }

    private function releaseLock(): void
    {
        if (null !== $this->lock && method_exists($this->lock, 'unlock')) {
            $this->lock->unlock();
        }
    }

    private function touch(string $key): void
    {
        unset($this->order[$key]);
        $this->order[$key] = true;
    }
}
