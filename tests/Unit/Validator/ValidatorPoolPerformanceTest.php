<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function microtime;
use function sprintf;

#[CoversClass(ValidatorPool::class)]
final class ValidatorPoolPerformanceTest extends TestCase
{
    private const int POOL_SIZE_DEFAULT = 128;

    private const int POOL_SIZE_SMALL = 32;

    private const int TOUCH_ITERATIONS = 1000;

    private const int SCALING_ITERATIONS = 5000;

    private const float MAX_SINGLE_TOUCH_MS = 0.1;

    private const float MAX_AVG_TOUCH_MS = 0.05;

    private const float SCALING_RATIO_THRESHOLD = 3.0;

    #[Test]
    public function touch_performance_is_constant_time(): void
    {
        $pool = new ValidatorPool(self::POOL_SIZE_DEFAULT);

        for ($i = 0; $i < self::POOL_SIZE_DEFAULT; ++$i) {
            $pool->getOrCreate('key_' . $i, fn() => new stdClass());
        }

        $start = microtime(true);

        for ($i = 0; $i < self::TOUCH_ITERATIONS; ++$i) {
            $key = 'key_' . ($i % self::POOL_SIZE_DEFAULT);
            $pool->getOrCreate($key, fn() => new stdClass());
        }

        $avgDurationMs = ((microtime(true) - $start) * 1000.0) / (float) self::TOUCH_ITERATIONS;

        self::assertLessThan(
            self::MAX_SINGLE_TOUCH_MS,
            $avgDurationMs,
            sprintf(
                'Average cache hit exceeded threshold: %.4fms (max allowed: %.2fms)',
                $avgDurationMs,
                self::MAX_SINGLE_TOUCH_MS,
            ),
        );
    }

    #[Test]
    public function touch_time_does_not_scale_with_pool_size(): void
    {
        $smallPoolAvgMs = $this->measureCacheHitAverageMs(self::POOL_SIZE_SMALL);
        $largePoolAvgMs = $this->measureCacheHitAverageMs(self::POOL_SIZE_DEFAULT);

        self::assertLessThan(
            self::MAX_AVG_TOUCH_MS,
            $largePoolAvgMs,
            sprintf(
                'Average cache hit for pool size %d too slow: %.4fms (max allowed: %.2fms)',
                self::POOL_SIZE_DEFAULT,
                $largePoolAvgMs,
                self::MAX_AVG_TOUCH_MS,
            ),
        );

        $ratio = $smallPoolAvgMs > 0.0 ? $largePoolAvgMs / $smallPoolAvgMs : 1.0;

        self::assertLessThan(
            self::SCALING_RATIO_THRESHOLD,
            $ratio,
            sprintf(
                'Cache hit time scales with pool size (ratio %.2f: small=%.4fms, large=%.4fms) — expected O(1)',
                $ratio,
                $smallPoolAvgMs,
                $largePoolAvgMs,
            ),
        );
    }

    #[Test]
    public function lru_evicts_oldest_entry_at_default_capacity(): void
    {
        $pool = new ValidatorPool(self::POOL_SIZE_DEFAULT);

        $objects = $this->fillPoolWithTrackedObjects($pool);

        $pool->getOrCreate('key_new', fn() => new stdClass());

        self::assertSame(
            $objects[1],
            $pool->getOrCreate('key_1', fn() => new stdClass()),
            'key_1 should still be cached after single eviction',
        );

        self::assertNotSame(
            $objects[0],
            $pool->getOrCreate('key_0', fn() => new stdClass()),
            'key_0 should have been evicted as the oldest entry',
        );
    }

    #[Test]
    public function lru_access_promotes_entry_at_default_capacity(): void
    {
        $pool = new ValidatorPool(self::POOL_SIZE_DEFAULT);

        $objects = $this->fillPoolWithTrackedObjects($pool);

        $pool->getOrCreate('key_0', fn() => new stdClass());

        $pool->getOrCreate('key_new', fn() => new stdClass());

        self::assertSame(
            $objects[0],
            $pool->getOrCreate('key_0', fn() => new stdClass()),
            'key_0 was promoted and should still be cached',
        );

        self::assertNotSame(
            $objects[1],
            $pool->getOrCreate('key_1', fn() => new stdClass()),
            'key_1 should have been evicted after key_0 was promoted',
        );
    }

    #[Test]
    public function lru_mixed_access_pattern_preserves_eviction_order(): void
    {
        $pool = new ValidatorPool(self::POOL_SIZE_DEFAULT);

        $objects = $this->fillPoolWithTrackedObjects($pool);

        for ($i = 0; $i < 64; ++$i) {
            $pool->getOrCreate('key_' . ($i * 2), fn() => new stdClass());
        }

        $pool->getOrCreate('key_new_1', fn() => new stdClass());
        $pool->getOrCreate('key_new_2', fn() => new stdClass());

        self::assertSame(
            $objects[0],
            $pool->getOrCreate('key_0', fn() => new stdClass()),
            'key_0 was touched and should still be cached',
        );

        self::assertNotSame(
            $objects[1],
            $pool->getOrCreate('key_1', fn() => new stdClass()),
            'key_1 was not touched and should have been evicted',
        );
    }

    /**
     * @return array<int, stdClass>
     */
    private function fillPoolWithTrackedObjects(ValidatorPool $pool): array
    {
        $objects = [];

        for ($i = 0; $i < self::POOL_SIZE_DEFAULT; ++$i) {
            $obj = new stdClass();
            $objects[$i] = $obj;
            $pool->getOrCreate('key_' . $i, fn() => $obj);
        }

        return $objects;
    }

    private function measureCacheHitAverageMs(int $poolSize): float
    {
        $pool = new ValidatorPool($poolSize);

        for ($i = 0; $i < $poolSize; ++$i) {
            $pool->getOrCreate('key_' . $i, fn() => new stdClass());
        }

        $start = microtime(true);

        for ($i = 0; $i < self::SCALING_ITERATIONS; ++$i) {
            $key = 'key_' . ($i % $poolSize);
            $pool->getOrCreate($key, fn() => new stdClass());
        }

        return ((microtime(true) - $start) * 1000.0) / (float) self::SCALING_ITERATIONS;
    }
}
