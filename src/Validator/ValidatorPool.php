<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

final class ValidatorPool
{
    /** @var array<string, object> */
    private array $pool = [];

    /**
     * @template T of object
     * @param callable(): T $factory
     * @return T
     */
    public function getOrCreate(string $key, callable $factory): object
    {
        if (isset($this->pool[$key])) {
            /** @var T */
            return $this->pool[$key];
        }

        $instance = $factory();
        $this->pool[$key] = $instance;

        return $instance;
    }
}
