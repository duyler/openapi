<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Test\Support\Psr7RequestStubFactory;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventIntegrationTest extends TestCase
{
    use Psr7RequestStubFactory;

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

        $request = $this->createStubRequest('GET', '/pets');
        try {
            $validator->validateRequest($request);
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

        $request = $this->createStubRequest('GET', '/pets');
        try {
            $validator->validateRequest($request);
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

        $request = $this->createStubRequest('POST', '/pets', ['Content-Type' => 'application/json'], '{}');

        $this->expectException(Exception::class);
        $validator->validateRequest($request);

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

        $request = $this->createStubRequest('GET', '/pets');
        try {
            $validator->validateRequest($request);
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

        $request = $this->createStubRequest('GET', '/pets');
        try {
            $validator->validateRequest($request);
        } catch (Exception) {
        }

        self::assertTrue($success);
    }
}
