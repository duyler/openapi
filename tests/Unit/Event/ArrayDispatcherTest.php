<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Event;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class ArrayDispatcherTest extends TestCase
{
    #[Test]
    public function dispatch_calls_listeners(): void
    {
        $called = false;
        $listener = function (object $event) use (&$called): void {
            $called = true;
        };

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [$listener],
        ]);

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $dispatcher->dispatch($event);

        self::assertTrue($called);
    }

    #[Test]
    public function dispatch_returns_event(): void
    {
        $dispatcher = new ArrayDispatcher();

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function dispatch_does_not_fail_when_no_listeners(): void
    {
        $dispatcher = new ArrayDispatcher();

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function listen_adds_listener(): void
    {
        $called = false;
        $listener = function (object $event) use (&$called): void {
            $called = true;
        };

        $dispatcher = new ArrayDispatcher();
        $dispatcher = $dispatcher->listen(ValidationStartedEvent::class, $listener);

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $dispatcher->dispatch($event);

        self::assertTrue($called);
    }

    #[Test]
    public function listen_returns_new_instance(): void
    {
        $dispatcher1 = new ArrayDispatcher();
        $listener = function (object $event): void {};

        $dispatcher2 = $dispatcher1->listen(ValidationStartedEvent::class, $listener);

        self::assertNotSame($dispatcher1, $dispatcher2);
    }

    #[Test]
    public function dispatch_calls_multiple_listeners(): void
    {
        $callCount = 0;
        $listener1 = function (object $event) use (&$callCount): void {
            $callCount++;
        };
        $listener2 = function (object $event) use (&$callCount): void {
            $callCount++;
        };

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [$listener1, $listener2],
        ]);

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $dispatcher->dispatch($event);

        self::assertSame(2, $callCount);
    }

    #[Test]
    public function dispatch_propagates_runtime_exception_thrown_by_listener(): void
    {
        $listener = function (object $event): never {
            throw new RuntimeException('listener failure');
        };

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [$listener],
        ]);

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $caught = null;
        try {
            $dispatcher->dispatch($event);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('listener failure', $caught->getMessage());
    }

    #[Test]
    public function dispatch_stops_at_first_throwing_listener_and_does_not_call_subsequent(): void
    {
        $secondListenerCalled = false;
        $firstListener = function (object $event): never {
            throw new RuntimeException('first listener aborts');
        };
        $secondListener = function (object $event) use (&$secondListenerCalled): void {
            $secondListenerCalled = true;
        };

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [$firstListener, $secondListener],
        ]);

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $caught = null;
        try {
            $dispatcher->dispatch($event);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('first listener aborts', $caught->getMessage());
        self::assertFalse($secondListenerCalled);
    }

    #[Test]
    public function dispatch_propagates_exception_with_original_message_and_code(): void
    {
        $listener = function (object $event): never {
            throw new RuntimeException('custom message', 42);
        };

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [$listener],
        ]);

        $request = $this->createStub(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $caught = null;
        try {
            $dispatcher->dispatch($event);
        } catch (RuntimeException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('custom message', $caught->getMessage());
        self::assertSame(42, $caught->getCode());
    }

    #[Test]
    public function dispatch_skips_remaining_listeners_when_stoppable_event_propagation_stopped(): void
    {
        $event = new class implements StoppableEventInterface {
            public bool $stopped = false;

            public function stop(): void
            {
                $this->stopped = true;
            }

            public function isPropagationStopped(): bool
            {
                return $this->stopped;
            }
        };

        $listener1Called = false;
        $listener2Called = false;

        $listener1 = function (object $event) use (&$listener1Called): void {
            $listener1Called = true;
            $event->stop();
        };
        $listener2 = function (object $event) use (&$listener2Called): void {
            $listener2Called = true;
        };

        $dispatcher = new ArrayDispatcher([$event::class => [$listener1, $listener2]]);

        $dispatcher->dispatch($event);

        self::assertTrue($listener1Called);
        self::assertFalse($listener2Called);
    }

    #[Test]
    public function dispatch_calls_all_listeners_when_stoppable_event_propagation_not_stopped(): void
    {
        $event = new class implements StoppableEventInterface {
            public function isPropagationStopped(): bool
            {
                return false;
            }
        };

        $callCount = 0;
        $listener1 = function (object $event) use (&$callCount): void {
            $callCount++;
        };
        $listener2 = function (object $event) use (&$callCount): void {
            $callCount++;
        };

        $dispatcher = new ArrayDispatcher([$event::class => [$listener1, $listener2]]);

        $dispatcher->dispatch($event);

        self::assertSame(2, $callCount);
    }

    #[Test]
    public function dispatch_calls_all_listeners_when_event_is_not_stoppable(): void
    {
        $event = new class {};

        $callCount = 0;
        $listener1 = function (object $event) use (&$callCount): void {
            $callCount++;
        };
        $listener2 = function (object $event) use (&$callCount): void {
            $callCount++;
        };

        $dispatcher = new ArrayDispatcher([$event::class => [$listener1, $listener2]]);

        $dispatcher->dispatch($event);

        self::assertSame(2, $callCount);
    }

    #[Test]
    public function dispatch_checks_is_propagation_stopped_before_first_listener(): void
    {
        $event = new class implements StoppableEventInterface {
            public function isPropagationStopped(): bool
            {
                return true;
            }
        };

        $listenerCalled = false;
        $listener = function (object $event) use (&$listenerCalled): void {
            $listenerCalled = true;
        };

        $dispatcher = new ArrayDispatcher([$event::class => [$listener]]);

        $dispatcher->dispatch($event);

        self::assertFalse($listenerCalled);
    }
}
