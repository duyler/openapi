<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ArrayObject;
use DateTime;
use WeakReference;
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
        $instance = $this->pool->getOrCreate(fn() => new stdClass());

        self::assertInstanceOf(stdClass::class, $instance);
    }

    #[Test]
    public function getOrCreate_returns_cached_instance(): void
    {
        $object = new stdClass();
        $factory = fn() => $object;
        $instance1 = $this->pool->getOrCreate($factory);
        $instance2 = $this->pool->getOrCreate($factory);

        self::assertSame($instance1, $instance2);
    }

    #[Test]
    public function getOrCreate_with_same_factory_result(): void
    {
        $object = new stdClass();
        $instance1 = $this->pool->getOrCreate(fn() => $object);
        $instance2 = $this->pool->getOrCreate(fn() => $object);

        self::assertSame($instance1, $instance2);
    }

    #[Test]
    public function getOrCreate_with_different_factory_results(): void
    {
        $instance1 = $this->pool->getOrCreate(fn() => new stdClass());
        $instance2 = $this->pool->getOrCreate(fn() => new stdClass());

        self::assertNotSame($instance1, $instance2);
    }

    #[Test]
    public function getOrCreate_multiple_calls_same_object(): void
    {
        $object = new stdClass();
        $instance1 = $this->pool->getOrCreate(fn() => $object);
        $instance2 = $this->pool->getOrCreate(fn() => $object);
        $instance3 = $this->pool->getOrCreate(fn() => $object);

        self::assertSame($instance1, $instance2);
        self::assertSame($instance2, $instance3);
    }

    #[Test]
    public function count_returns_zero_for_empty_pool(): void
    {
        self::assertSame(0, $this->pool->count());
    }

    #[Test]
    public function count_returns_number_of_instances(): void
    {
        $this->pool->getOrCreate(fn() => new stdClass());
        $this->pool->getOrCreate(fn() => new DateTime());

        self::assertSame(2, $this->pool->count());
    }

    #[Test]
    public function count_decreases_after_gc(): void
    {
        $object1 = new stdClass();
        $object2 = new stdClass();
        $this->pool->getOrCreate(fn() => $object1);
        $this->pool->getOrCreate(fn() => $object2);

        self::assertSame(2, $this->pool->count());

        unset($object1, $object2);
        gc_collect_cycles();

        self::assertSame(0, $this->pool->count());
    }

    #[Test]
    public function count_with_multiple_instances(): void
    {
        $this->pool->getOrCreate(fn() => new stdClass());
        $this->pool->getOrCreate(fn() => new DateTime());
        $this->pool->getOrCreate(fn() => new ArrayObject());

        self::assertSame(3, $this->pool->count());
    }

    #[Test]
    public function weakmap_clears_on_gc(): void
    {
        $instance = $this->pool->getOrCreate(fn() => new stdClass());

        self::assertSame(1, $this->pool->count());

        unset($instance);
        gc_collect_cycles();

        self::assertSame(0, $this->pool->count());
    }

    #[Test]
    public function weakmap_with_object_destruction(): void
    {
        $instance = $this->pool->getOrCreate(fn() => new stdClass());

        $ref = WeakReference::create($instance);

        self::assertTrue($ref->get() !== null);

        unset($instance);
        gc_collect_cycles();

        self::assertNull($ref->get());
    }

    #[Test]
    public function weakmap_maintains_strict_references(): void
    {
        $object1 = new stdClass();
        $object2 = new stdClass();

        $instance1 = $this->pool->getOrCreate(fn() => $object1);
        $instance2 = $this->pool->getOrCreate(fn() => $object2);

        self::assertNotSame($instance1, $instance2);
        self::assertSame(2, $this->pool->count());
    }
}
