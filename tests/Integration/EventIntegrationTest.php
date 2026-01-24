<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class EventIntegrationTest extends TestCase
{
    #[Test]
    public function validation_started_event_dispatched(): void
    {
        $dispatched = false;
        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function (ValidationStartedEvent $event) use (&$dispatched): void {
                    $dispatched = true;
                    self::assertSame('/pets', $event->path);
                    self::assertSame('GET', $event->method);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->createPsr7Request('/pets', 'GET');
        try {
            $validator->validateRequest($request, '/pets', 'GET');
        } catch (Exception) {
        }

        self::assertTrue($dispatched);
    }

    #[Test]
    public function validation_finished_event_contains_duration(): void
    {
        $duration = null;
        $dispatcher = new ArrayDispatcher([
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$duration): void {
                    $duration = $event->duration;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->createPsr7Request('/pets', 'GET');
        try {
            $validator->validateRequest($request, '/pets', 'GET');
        } catch (Exception) {
        }

        self::assertNotNull($duration);
        self::assertGreaterThan(0, $duration);
    }

    #[Test]
    public function validation_error_event_dispatched_on_exception(): void
    {
        $dispatched = false;
        $dispatcher = new ArrayDispatcher([
            ValidationErrorEvent::class => [
                function (ValidationErrorEvent $event) use (&$dispatched): void {
                    $dispatched = true;
                    self::assertInstanceOf(Exception::class, $event->exception);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->createPsr7Request('/pets', 'POST', [], '{}');

        $this->expectException(Exception::class);
        $validator->validateRequest($request, '/pets', 'POST');

        self::assertTrue($dispatched);
    }

    #[Test]
    public function custom_listener_receives_events(): void
    {
        $events = [];
        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function (ValidationStartedEvent $event) use (&$events): void {
                    $events[] = 'started';
                },
            ],
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$events): void {
                    $events[] = 'finished';
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->createPsr7Request('/pets', 'GET');
        try {
            $validator->validateRequest($request, '/pets', 'GET');
        } catch (Exception) {
        }

        self::assertSame(['started', 'finished'], $events);
    }

    #[Test]
    public function finished_event_success_true_on_valid_request(): void
    {
        $success = null;
        $dispatcher = new ArrayDispatcher([
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$success): void {
                    $success = $event->success;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->createPsr7Request('/pets', 'GET');
        try {
            $validator->validateRequest($request, '/pets', 'GET');
        } catch (Exception) {
        }

        self::assertTrue($success);
    }

    private function createPsr7Request(
        string $uri,
        string $method,
        array $headers = [],
        string $body = '',
    ): object {
        $request = $this->createMock(ServerRequestInterface::class);

        $request
            ->method('getMethod')
            ->willReturn($method);

        $uriMock = $this->createMock(UriInterface::class);
        $uriMock
            ->method('getPath')
            ->willReturn($uri);
        $uriMock
            ->method('getQuery')
            ->willReturn('');

        $request
            ->method('getUri')
            ->willReturn($uriMock);

        $request
            ->method('getHeaders')
            ->willReturn($headers);

        $request
            ->method('getHeaderLine')
            ->willReturnCallback(function ($headerName) use ($headers) {
                if ('Content-Type' === $headerName) {
                    return 'application/json';
                }

                if ('Cookie' === $headerName) {
                    return '';
                }

                return '';
            });

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn($body);

        $request
            ->method('getBody')
            ->willReturn($stream);

        return $request;
    }
}
