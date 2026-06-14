<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Event;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class ValidationLifecycleTest extends TestCase
{
    private const string LIFECYCLE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Lifecycle API
  version: 1.0.0
paths:
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Item'
      responses:
        '201':
          description: Created
components:
  schemas:
    Item:
      type: object
      required:
        - name
      properties:
        name:
          type: string
        oldField:
          type: string
          deprecated: true
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function lifecycle_started_then_finished_on_successful_validation(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $warningEvent = null;
        $dispatcher = $this->createLifecycleDispatcher(
            $sequence,
            $startedEvent,
            $finishedEvent,
            $errorEvent,
            $warningEvent,
        );

        $validator = $this->buildLifecycleValidator($dispatcher);

        $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        self::assertSame(['started', 'finished'], $sequence);
        self::assertNotNull($startedEvent);
        self::assertNotNull($finishedEvent);
        self::assertSame('POST', $startedEvent->method);
        self::assertSame('/items', $startedEvent->path);
        self::assertTrue($finishedEvent->success);
        self::assertSame('POST', $finishedEvent->method);
        self::assertSame('/items', $finishedEvent->path);
        self::assertGreaterThanOrEqual(0.0, $finishedEvent->duration);
    }

    #[Test]
    public function lifecycle_started_then_finished_then_error_on_invalid_request(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $warningEvent = null;
        $dispatcher = $this->createLifecycleDispatcher(
            $sequence,
            $startedEvent,
            $finishedEvent,
            $errorEvent,
            $warningEvent,
        );

        $validator = $this->buildLifecycleValidator($dispatcher);

        $exceptionCaught = false;

        try {
            $validator->validateRequest($this->createItemRequest('{}'));
            self::fail('Expected ValidationException for missing required "name" property');
        } catch (ValidationException) {
            $exceptionCaught = true;
        }

        self::assertTrue($exceptionCaught);
        self::assertSame(['started', 'finished', 'error'], $sequence);
        self::assertNotNull($finishedEvent);
        self::assertNotNull($errorEvent);
        self::assertFalse($finishedEvent->success);
        self::assertSame('POST', $errorEvent->method);
        self::assertSame('/items', $errorEvent->path);
        self::assertInstanceOf(ValidationException::class, $errorEvent->exception);
    }

    #[Test]
    public function lifecycle_started_then_warning_then_finished_on_deprecated_property(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $warningEvent = null;
        $dispatcher = $this->createLifecycleDispatcher(
            $sequence,
            $startedEvent,
            $finishedEvent,
            $errorEvent,
            $warningEvent,
        );

        $validator = $this->buildLifecycleValidator($dispatcher);

        $validator->validateRequest($this->createItemRequest('{"name":"widget","oldField":"legacy"}'));

        self::assertSame(['started', 'warning', 'finished'], $sequence);
        self::assertNotNull($warningEvent);
        self::assertNotNull($finishedEvent);
        self::assertSame('oldField', $warningEvent->propertyName);
        self::assertSame('Property "oldField" is deprecated', $warningEvent->message);
        self::assertTrue($finishedEvent->success);
    }

    #[Test]
    public function lifecycle_only_started_and_finished_on_valid_request_without_deprecated(): void
    {
        $sequence = [];
        $dispatcher = $this->createSequenceDispatcher($sequence);

        $validator = $this->buildLifecycleValidator($dispatcher);

        $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        self::assertSame(['started', 'finished'], $sequence);
    }

    #[Test]
    public function lifecycle_started_event_carries_request_path_and_method(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $warningEvent = null;
        $dispatcher = $this->createLifecycleDispatcher(
            $sequence,
            $startedEvent,
            $finishedEvent,
            $errorEvent,
            $warningEvent,
        );

        $validator = $this->buildLifecycleValidator($dispatcher);

        $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        self::assertNotNull($startedEvent);
        self::assertSame('/items', $startedEvent->path);
        self::assertSame('POST', $startedEvent->method);
        self::assertNotNull($startedEvent->request);
    }

    #[Test]
    public function lifecycle_finished_duration_is_non_negative(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $warningEvent = null;
        $dispatcher = $this->createLifecycleDispatcher(
            $sequence,
            $startedEvent,
            $finishedEvent,
            $errorEvent,
            $warningEvent,
        );

        $validator = $this->buildLifecycleValidator($dispatcher);

        $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        self::assertNotNull($finishedEvent);
        self::assertGreaterThanOrEqual(0.0, $finishedEvent->duration);
    }

    /**
     * @param list<string>             $sequence
     * @param ?ValidationStartedEvent  $started
     * @param ?ValidationFinishedEvent $finished
     * @param ?ValidationErrorEvent    $error
     * @param ?ValidationWarningEvent  $warning
     */
    private function createLifecycleDispatcher(
        array &$sequence,
        ?ValidationStartedEvent &$started,
        ?ValidationFinishedEvent &$finished,
        ?ValidationErrorEvent &$error,
        ?ValidationWarningEvent &$warning,
    ): ArrayDispatcher {
        return new ArrayDispatcher([
            ValidationStartedEvent::class => [
                static function (ValidationStartedEvent $e) use (&$sequence, &$started): void {
                    $sequence[] = 'started';
                    $started = $e;
                },
            ],
            ValidationFinishedEvent::class => [
                static function (ValidationFinishedEvent $e) use (&$sequence, &$finished): void {
                    $sequence[] = 'finished';
                    $finished = $e;
                },
            ],
            ValidationErrorEvent::class => [
                static function (ValidationErrorEvent $e) use (&$sequence, &$error): void {
                    $sequence[] = 'error';
                    $error = $e;
                },
            ],
            ValidationWarningEvent::class => [
                static function (ValidationWarningEvent $e) use (&$sequence, &$warning): void {
                    $sequence[] = 'warning';
                    $warning = $e;
                },
            ],
        ]);
    }

    /**
     * @param list<string> $sequence
     */
    private function createSequenceDispatcher(array &$sequence): ArrayDispatcher
    {
        return new ArrayDispatcher([
            ValidationStartedEvent::class => [
                static function () use (&$sequence): void {
                    $sequence[] = 'started';
                },
            ],
            ValidationFinishedEvent::class => [
                static function () use (&$sequence): void {
                    $sequence[] = 'finished';
                },
            ],
            ValidationErrorEvent::class => [
                static function () use (&$sequence): void {
                    $sequence[] = 'error';
                },
            ],
            ValidationWarningEvent::class => [
                static function () use (&$sequence): void {
                    $sequence[] = 'warning';
                },
            ],
        ]);
    }

    private function buildLifecycleValidator(ArrayDispatcher $dispatcher): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::LIFECYCLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();
    }

    private function createItemRequest(string $body): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }
}
