<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class FullCycleEventsTest extends TestCase
{
    private const string CYCLE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Full Cycle Events API
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
          description: Item created
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Item'
  /ping:
    get:
      operationId: ping
      responses:
        '200':
          description: Pong
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Pong'
components:
  schemas:
    Item:
      type: object
      required:
        - name
      properties:
        name:
          type: string
        quantity:
          type: integer
    Pong:
      type: object
      required:
        - status
      properties:
        status:
          type: string
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function successful_request_cycle_emits_started_then_finished_in_order(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $dispatcher = $this->buildLifecycleDispatcher($sequence, $startedEvent, $finishedEvent, $errorEvent);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CYCLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();

        $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        self::assertSame(['started', 'finished'], $sequence);
        self::assertNotNull($startedEvent);
        self::assertNotNull($finishedEvent);
        self::assertInstanceOf(ValidationStartedEvent::class, $startedEvent);
        self::assertInstanceOf(ValidationFinishedEvent::class, $finishedEvent);
        self::assertSame('POST', $startedEvent->method);
        self::assertSame('/items', $startedEvent->path);
        self::assertSame('POST', $finishedEvent->method);
        self::assertSame('/items', $finishedEvent->path);
        self::assertTrue($finishedEvent->success);
        self::assertGreaterThanOrEqual(0.0, $finishedEvent->duration);
    }

    #[Test]
    public function successful_response_cycle_emits_started_then_finished_in_order(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $dispatcher = $this->buildLifecycleDispatcher($sequence, $startedEvent, $finishedEvent, $errorEvent);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CYCLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();

        $operation = $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;

        $response = $this->factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"widget"}'));

        $validator->validateResponse($response, $operation);

        self::assertSame(['started', 'finished'], $sequence);
        self::assertNotNull($startedEvent);
        self::assertNotNull($finishedEvent);
        self::assertSame('POST', $startedEvent->method);
        self::assertSame('/items', $startedEvent->path);
        self::assertTrue($finishedEvent->success);
    }

    #[Test]
    public function full_request_response_cycle_emits_four_events_two_pairs(): void
    {
        $sequence = [];
        $dispatcher = $this->buildSequenceDispatcher($sequence);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CYCLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();

        $operation = $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));

        $response = $this->factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"name":"widget"}'));

        $validator->validateResponse($response, $operation);

        self::assertSame(
            ['started', 'finished', 'started', 'finished'],
            $sequence,
        );
    }

    #[Test]
    public function request_error_emits_started_finished_error_and_specific_exception_type(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $dispatcher = $this->buildLifecycleDispatcher($sequence, $startedEvent, $finishedEvent, $errorEvent);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CYCLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();

        $caught = null;
        try {
            $validator->validateRequest($this->createItemRequest('{}'));
            self::fail('Expected ValidationException for missing required "name" property');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(['started', 'finished', 'error'], $sequence);
        self::assertNotNull($startedEvent);
        self::assertNotNull($finishedEvent);
        self::assertNotNull($errorEvent);
        self::assertFalse($finishedEvent->success);
        self::assertSame('POST', $errorEvent->method);
        self::assertSame('/items', $errorEvent->path);
        self::assertInstanceOf(ValidationException::class, $errorEvent->exception);

        $errors = $errorEvent->exception->getErrors();
        self::assertCount(1, $errors);
        self::assertInstanceOf(RequiredError::class, $errors[0]);
        self::assertSame('name', $errors[0]->params()['property']);
    }

    #[Test]
    public function response_error_emits_started_finished_error_with_validation_exception(): void
    {
        $sequence = [];
        $startedEvent = null;
        $finishedEvent = null;
        $errorEvent = null;
        $dispatcher = $this->buildLifecycleDispatcher($sequence, $startedEvent, $finishedEvent, $errorEvent);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CYCLE_SPEC)
            ->withEventDispatcher($dispatcher)
            ->build();

        $operation = $validator->validateRequest($this->createItemRequest('{"name":"widget"}'));
        $sequence = [];

        $invalidResponse = $this->factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{}'));

        $caught = null;
        try {
            $validator->validateResponse($invalidResponse, $operation);
            self::fail('Expected ValidationException for response missing required "name"');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(['started', 'finished', 'error'], $sequence);
        self::assertNotNull($errorEvent);
        self::assertFalse($finishedEvent->success);
        self::assertSame('POST', $errorEvent->method);
        self::assertSame('/items', $errorEvent->path);
        self::assertInstanceOf(ValidationException::class, $errorEvent->exception);

        $errors = $errorEvent->exception->getErrors();
        self::assertCount(1, $errors);
        self::assertInstanceOf(RequiredError::class, $errors[0]);
        self::assertSame('name', $errors[0]->params()['property']);
    }

    private function createItemRequest(string $body): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }

    /**
     * @param list<string>             $sequence
     * @param ?ValidationStartedEvent  $started
     * @param ?ValidationFinishedEvent $finished
     * @param ?ValidationErrorEvent    $error
     */
    private function buildLifecycleDispatcher(
        array &$sequence,
        ?ValidationStartedEvent &$started,
        ?ValidationFinishedEvent &$finished,
        ?ValidationErrorEvent &$error,
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
        ]);
    }

    /**
     * @param list<string> $sequence
     */
    private function buildSequenceDispatcher(array &$sequence): ArrayDispatcher
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
        ]);
    }
}
