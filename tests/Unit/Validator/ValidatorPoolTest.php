<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\ValidatorPool;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ValidatorPoolTest extends TestCase
{
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
    }

    #[Test]
    public function getOrCreate_creates_new_instance(): void
    {
        $instance = $this->pool->getOrCreate('key_a', fn() => new stdClass());

        self::assertInstanceOf(stdClass::class, $instance);
    }

    #[Test]
    public function getOrCreate_returns_same_instance_for_same_key(): void
    {
        $instance1 = $this->pool->getOrCreate('key_a', fn() => new stdClass());
        $instance2 = $this->pool->getOrCreate('key_a', fn() => new stdClass());

        self::assertSame($instance1, $instance2);
    }

    #[Test]
    public function getOrCreate_returns_different_instances_for_different_keys(): void
    {
        $instance1 = $this->pool->getOrCreate('key_a', fn() => new stdClass());
        $instance2 = $this->pool->getOrCreate('key_b', fn() => new stdClass());

        self::assertNotSame($instance1, $instance2);
    }

    #[Test]
    public function getOrCreate_does_not_call_factory_on_cache_hit(): void
    {
        $callCount = 0;
        $factory = function () use (&$callCount) {
            ++$callCount;

            return new stdClass();
        };

        $this->pool->getOrCreate('key_a', $factory);
        $this->pool->getOrCreate('key_a', $factory);

        self::assertSame(1, $callCount);
    }

    #[Test]
    public function getOrCreate_returns_strictly_same_object(): void
    {
        $object = new stdClass();
        $object->value = 42;

        $result = $this->pool->getOrCreate('key_a', fn() => $object);

        self::assertSame($object, $result);
        self::assertSame(42, $result->value);
    }

    #[Test]
    public function constructor_accepts_custom_max_size(): void
    {
        $pool = new ValidatorPool(3);

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => new stdClass());
        $pool->getOrCreate('c', fn() => new stdClass());

        $fourth = new stdClass();
        $result = $pool->getOrCreate('d', fn() => $fourth);

        self::assertSame($fourth, $result);
    }

    #[Test]
    public function constructor_throws_for_zero_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ValidatorPool(0);
    }

    #[Test]
    public function constructor_throws_for_negative_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ValidatorPool(-1);
    }

    #[Test]
    public function lru_evicts_least_recently_used(): void
    {
        $pool = new ValidatorPool(2);

        $objectB = new stdClass();
        $objectC = new stdClass();

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => $objectB);
        $pool->getOrCreate('c', fn() => $objectC);

        self::assertSame($objectB, $pool->getOrCreate('b', fn() => new stdClass()));
        self::assertSame($objectC, $pool->getOrCreate('c', fn() => new stdClass()));
    }

    #[Test]
    public function lru_access_promotes_entry(): void
    {
        $pool = new ValidatorPool(2);

        $objectA = new stdClass();
        $objectC = new stdClass();

        $pool->getOrCreate('a', fn() => $objectA);
        $pool->getOrCreate('b', fn() => new stdClass());
        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('c', fn() => $objectC);

        self::assertSame($objectA, $pool->getOrCreate('a', fn() => new stdClass()));
        self::assertSame($objectC, $pool->getOrCreate('c', fn() => new stdClass()));
    }

    #[Test]
    public function lru_single_entry_pool(): void
    {
        $pool = new ValidatorPool(1);

        $objectB = new stdClass();

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => $objectB);

        self::assertSame($objectB, $pool->getOrCreate('b', fn() => new stdClass()));
    }

    #[Test]
    public function lru_does_not_evict_when_at_capacity(): void
    {
        $pool = new ValidatorPool(3);

        $objectA = new stdClass();
        $objectB = new stdClass();
        $objectC = new stdClass();

        $pool->getOrCreate('a', fn() => $objectA);
        $pool->getOrCreate('b', fn() => $objectB);
        $pool->getOrCreate('c', fn() => $objectC);

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => new stdClass());
        $pool->getOrCreate('c', fn() => new stdClass());

        self::assertSame($objectA, $pool->getOrCreate('a', fn() => new stdClass()));
        self::assertSame($objectB, $pool->getOrCreate('b', fn() => new stdClass()));
        self::assertSame($objectC, $pool->getOrCreate('c', fn() => new stdClass()));
    }

    #[Test]
    public function clear_removes_all_entries(): void
    {
        $pool = new ValidatorPool(10);

        $callCount = 0;
        $factory = function () use (&$callCount) {
            ++$callCount;

            return new stdClass();
        };

        $pool->getOrCreate('a', $factory);
        $pool->getOrCreate('b', $factory);
        $pool->getOrCreate('a', $factory);

        self::assertSame(2, $callCount);

        $pool->clear();

        $pool->getOrCreate('a', $factory);
        $pool->getOrCreate('b', $factory);

        self::assertSame(4, $callCount);
    }

    #[Test]
    public function clear_allows_reuse_after_eviction(): void
    {
        $pool = new ValidatorPool(2);

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => new stdClass());

        $pool->clear();

        $objectC = new stdClass();
        $result = $pool->getOrCreate('c', fn() => $objectC);

        self::assertSame($objectC, $result);
    }

    #[Test]
    public function clear_resets_capacity_tracking(): void
    {
        $pool = new ValidatorPool(2);

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => new stdClass());
        $pool->clear();

        $objectB = new stdClass();
        $objectC = new stdClass();

        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => $objectB);
        $pool->getOrCreate('c', fn() => $objectC);

        self::assertSame($objectB, $pool->getOrCreate('b', fn() => new stdClass()));
        self::assertSame($objectC, $pool->getOrCreate('c', fn() => new stdClass()));
    }

    #[Test]
    public function clear_on_empty_pool_is_noop(): void
    {
        $pool = new ValidatorPool();

        $pool->clear();

        $object = new stdClass();
        $result = $pool->getOrCreate('key', fn() => $object);

        self::assertSame($object, $result);
    }

    #[Test]
    public function lru_eviction_order_after_multiple_accesses(): void
    {
        $pool = new ValidatorPool(3);

        $objA = new stdClass();
        $objB = new stdClass();
        $objD = new stdClass();

        $pool->getOrCreate('a', fn() => $objA);
        $pool->getOrCreate('b', fn() => $objB);
        $pool->getOrCreate('c', fn() => new stdClass());
        $pool->getOrCreate('a', fn() => new stdClass());
        $pool->getOrCreate('b', fn() => new stdClass());
        $pool->getOrCreate('d', fn() => $objD);

        self::assertSame($objA, $pool->getOrCreate('a', fn() => new stdClass()));
        self::assertSame($objB, $pool->getOrCreate('b', fn() => new stdClass()));
        self::assertSame($objD, $pool->getOrCreate('d', fn() => new stdClass()));
    }

    #[Test]
    public function factory_is_called_exactly_once_per_evicted_key(): void
    {
        $pool = new ValidatorPool(2);

        $callCount = 0;
        $factory = function () use (&$callCount) {
            ++$callCount;

            return new stdClass();
        };

        $pool->getOrCreate('a', $factory);
        $pool->getOrCreate('b', $factory);
        $pool->getOrCreate('c', $factory);

        $pool->getOrCreate('a', $factory);

        self::assertSame(4, $callCount);
    }

    #[Test]
    public function default_max_size_is_128(): void
    {
        $pool = new ValidatorPool();

        $objects = [];
        for ($i = 0; $i < 130; ++$i) {
            $obj = new stdClass();
            $obj->index = $i;
            $objects[$i] = $obj;
            $pool->getOrCreate('key_' . $i, fn() => $obj);
        }

        self::assertSame($objects[2], $pool->getOrCreate('key_2', fn() => new stdClass()));
        self::assertNotSame($objects[0], $pool->getOrCreate('key_0', fn() => new stdClass()));
    }
}
