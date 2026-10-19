<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Event\ValidationWarningEvent;

final class TestEventBuilders
{
    private const array DEFAULT_SEQUENCE_EVENTS = [
        ValidationStartedEvent::class,
        ValidationFinishedEvent::class,
        ValidationErrorEvent::class,
        ValidationWarningEvent::class,
    ];

    private const array SEQUENCE_EVENT_LABELS = [
        ValidationStartedEvent::class => 'started',
        ValidationFinishedEvent::class => 'finished',
        ValidationErrorEvent::class => 'error',
        ValidationWarningEvent::class => 'warning',
    ];

    /**
     * @param list<string> $sequence
     * @param list<class-string> $eventClasses
     */
    public static function sequenceDispatcher(
        array &$sequence,
        array $eventClasses = self::DEFAULT_SEQUENCE_EVENTS,
    ): ArrayDispatcher {
        $listeners = [];

        foreach ($eventClasses as $eventClass) {
            $listeners[$eventClass] = [
                static function () use (&$sequence, $eventClass): void {
                    $sequence[] = self::SEQUENCE_EVENT_LABELS[$eventClass];
                },
            ];
        }

        return new ArrayDispatcher($listeners);
    }
}
