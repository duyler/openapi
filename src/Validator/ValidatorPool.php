<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use WeakMap;

final readonly class ValidatorPool
{
    /** @var WeakMap<object, mixed> */
    public WeakMap $pool;

    public function __construct()
    {
        $this->pool = new WeakMap();
    }

    /**
     * @template T of object
     * @param callable(): T $factory
     * @return T
     */
    public function getOrCreate(callable $factory): object
    {
        $instance = $factory();

        if ($this->pool->offsetExists($instance)) {
            /** @var T */
            return $this->pool->offsetGet($instance);
        }

        $this->pool->offsetSet($instance, $instance);

        return $instance;
    }

    public function count(): int
    {
        return $this->pool->count();
    }
}
