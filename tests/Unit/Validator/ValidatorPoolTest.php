<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\ValidatorPool;
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
    public function count_returns_zero_for_empty_pool(): void
    {
        self::assertSame(0, $this->pool->count());
    }

    #[Test]
    public function count_returns_number_of_unique_keys(): void
    {
        $this->pool->getOrCreate('key_a', fn() => new stdClass());
        $this->pool->getOrCreate('key_b', fn() => new stdClass());

        self::assertSame(2, $this->pool->count());
    }

    #[Test]
    public function count_does_not_increase_on_duplicate_key(): void
    {
        $this->pool->getOrCreate('key_a', fn() => new stdClass());
        $this->pool->getOrCreate('key_a', fn() => new stdClass());

        self::assertSame(1, $this->pool->count());
    }
}
