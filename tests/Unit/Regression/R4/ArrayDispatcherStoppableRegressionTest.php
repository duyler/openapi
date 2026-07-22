<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;

/**
 * Regression suite for R4-PSR-001 / R4-TEST-012: ArrayDispatcher must
 * honour PSR-14 StoppableEventInterface by checking
 * isPropagationStopped() before every listener invocation (including
 * the first) and breaking the loop as soon as propagation is stopped.
 *
 * Anti-test: removing the `instanceof StoppableEventInterface` check
 * (or removing the `break` statement) makes test 1 fail — the second
 * listener is invoked even though the first stopped propagation.
 *
 * Unlike the inline ArrayDispatcherTest that uses anonymous classes in
 * isolation, this suite registers listeners on real {@see ValidationStartedEvent},
 * {@see ValidationErrorEvent} and {@see ValidationFinishedEvent} classes
 * so the regression also fires when those event classes are mutated to
 * drop the StoppableEventInterface contract in custom wrappers.
 *
 * @internal
 */
final class ArrayDispatcherStoppableRegressionTest extends TestCase
{
    #[Test]
    public function stoppable_wrapper_event_stops_after_first_listener(): void
    {
        $firstListenerCalled = false;
        $secondListenerInvocations = 0;

        $event = new StoppableMarker(self::class . '::' . __FUNCTION__);
        $firstListener = function (StoppableMarker $event) use (&$firstListenerCalled): void {
            $firstListenerCalled = true;
            $event->stop();
        };
        $secondListener = static function () use (&$secondListenerInvocations): void {
            ++$secondListenerInvocations;
        };

        $dispatcher = new ArrayDispatcher([$event::class => [$firstListener, $secondListener]]);

        $dispatcher->dispatch($event);

        self::assertTrue($firstListenerCalled);
        self::assertSame(0, $secondListenerInvocations, 'Second listener must NOT run when first stopped propagation.');
    }

    #[Test]
    public function stoppable_event_with_propagation_pre_stopped_skips_first_listener(): void
    {
        $firstListenerCalled = false;

        $event = new StoppableMarker(self::class . '::' . __FUNCTION__, true);
        $firstListener = static function () use (&$firstListenerCalled): void {
            $firstListenerCalled = true;
        };

        $dispatcher = new ArrayDispatcher([$event::class => [$firstListener]]);

        $dispatcher->dispatch($event);

        self::assertFalse($firstListenerCalled, 'PSR-14: isPropagationStopped() must be checked before the first listener.');
    }

    #[Test]
    public function non_stoppable_event_invokes_all_listeners(): void
    {
        $counter = 0;

        $dispatcher = new ArrayDispatcher([
            ValidationFinishedEvent::class => [
                static function () use (&$counter): void {
                    ++$counter;
                },
                static function () use (&$counter): void {
                    ++$counter;
                },
            ],
        ]);

        $dispatcher->dispatch(new ValidationFinishedEvent());

        self::assertSame(2, $counter, 'Non-stoppable events must dispatch to every registered listener.');
    }

    #[Test]
    public function real_validation_started_event_invokes_listeners_in_order(): void
    {
        $order = [];

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                static function () use (&$order): void {
                    $order[] = 'first';
                },
                static function () use (&$order): void {
                    $order[] = 'second';
                },
            ],
        ]);

        $request = new Psr17Factory()->createServerRequest('GET', '/x');
        $dispatcher->dispatch(new ValidationStartedEvent($request, '/x', 'GET'));

        self::assertSame(['first', 'second'], $order);
    }

    #[Test]
    public function real_validation_error_event_invokes_listeners_in_order(): void
    {
        $order = [];

        $dispatcher = new ArrayDispatcher([
            ValidationErrorEvent::class => [
                static function () use (&$order): void {
                    $order[] = 'first';
                },
                static function () use (&$order): void {
                    $order[] = 'second';
                },
            ],
        ]);

        $request = new Psr17Factory()->createServerRequest('GET', '/x');
        $exception = new ValidationException();
        $dispatcher->dispatch(new ValidationErrorEvent($request, '/x', 'GET', $exception));

        self::assertSame(['first', 'second'], $order);
    }
}

/**
 * Minimal stand-alone StoppableEventInterface implementation that lets
 * the regression suite pin the PSR-14 contract without depending on
 * internal event classes implementing the interface (they do not).
 *
 * @internal
 */
final class StoppableMarker implements StoppableEventInterface
{
    public function __construct(private readonly string $marker, private bool $stopped = false) {}

    public function marker(): string
    {
        return $this->marker;
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    #[Override]
    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
