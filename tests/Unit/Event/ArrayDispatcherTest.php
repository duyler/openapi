<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Event;

use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

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

        $request = $this->createMock(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $dispatcher->dispatch($event);

        self::assertTrue($called);
    }

    #[Test]
    public function dispatch_returns_event(): void
    {
        $dispatcher = new ArrayDispatcher();

        $request = $this->createMock(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $result = $dispatcher->dispatch($event);

        self::assertSame($event, $result);
    }

    #[Test]
    public function dispatch_does_not_fail_when_no_listeners(): void
    {
        $dispatcher = new ArrayDispatcher();

        $request = $this->createMock(ServerRequestInterface::class);
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

        $request = $this->createMock(ServerRequestInterface::class);
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

        $request = $this->createMock(ServerRequestInterface::class);
        $event = new ValidationStartedEvent($request, '/test', 'GET');

        $dispatcher->dispatch($event);

        self::assertSame(2, $callCount);
    }
}
