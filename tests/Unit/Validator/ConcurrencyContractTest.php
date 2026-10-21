<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Test\Support\CountingLock;
use Duyler\OpenApi\Validator\Internal\LruCache;
use Duyler\OpenApi\Validator\ValidatorPool;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class ConcurrencyContractTest extends TestCase
{
    #[Test]
    public function validator_pool_for_coroutine_runtime_factory_rejects_lock_without_methods(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ValidatorPool::forCoroutineRuntime(new stdClass());
    }

    #[Test]
    public function validator_pool_for_coroutine_runtime_factory_accepts_lock_with_methods(): void
    {
        $lock = new CountingLock();

        $pool = ValidatorPool::forCoroutineRuntime($lock);

        self::assertInstanceOf(ValidatorPool::class, $pool);
    }

    #[Test]
    public function validator_pool_for_coroutine_runtime_factory_propagates_max_size(): void
    {
        $lock = new CountingLock();

        $pool = ValidatorPool::forCoroutineRuntime($lock, maxSize: 64);

        $cacheProperty = new ReflectionProperty(ValidatorPool::class, 'cache');

        /** @var LruCache<string, object> $cache */
        $cache = $cacheProperty->getValue($pool);

        $maxSizeProperty = new ReflectionProperty(LruCache::class, 'maxSize');

        self::assertSame(64, $maxSizeProperty->getValue($cache));
    }

    #[Test]
    public function validator_pool_acquire_lock_invokes_lock_once_per_get_or_create(): void
    {
        $lock = new CountingLock();
        $pool = ValidatorPool::forCoroutineRuntime($lock);

        $pool->getOrCreate('shared', static fn(): stdClass => new stdClass());
        $pool->getOrCreate('shared', static fn(): stdClass => new stdClass());

        self::assertSame(2, $lock->lockCount, 'lock() must be invoked once per getOrCreate call');
        self::assertSame(2, $lock->unlockCount, 'unlock() must be invoked once per getOrCreate call');
    }

    #[Test]
    public function validator_pool_release_lock_invoked_on_clear(): void
    {
        $lock = new CountingLock();
        $pool = ValidatorPool::forCoroutineRuntime($lock);

        $pool->clear();

        self::assertSame(1, $lock->lockCount);
        self::assertSame(1, $lock->unlockCount);
    }
}
