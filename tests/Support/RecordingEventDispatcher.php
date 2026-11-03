<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    #[Override]
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
