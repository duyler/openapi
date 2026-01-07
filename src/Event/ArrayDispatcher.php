<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

readonly class ArrayDispatcher implements EventDispatcherInterface
{
    /**
     * @param array<string, array<int, callable>> $listeners
     */
    public function __construct(
        private readonly array $listeners = [],
    ) {}

    #[Override]
    public function dispatch(object $event): object
    {
        $eventName = $event::class;

        if (false === isset($this->listeners[$eventName])) {
            return $event;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            $listener($event);
        }

        return $event;
    }

    /**
     * @param callable(object): void $listener
     */
    public function listen(string $eventName, callable $listener): self
    {
        $listeners = $this->listeners[$eventName] ?? [];
        $listeners[] = $listener;

        $newListeners = $this->listeners;
        $newListeners[$eventName] = $listeners;

        return new self($newListeners);
    }
}
