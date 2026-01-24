<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;
use Exception;

final class OpenApiValidatorEventsTest extends TestCase
{
    #[Test]
    public function dispatches_events_on_successful_validation(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $events = [];
        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function ($event) use (&$events) {
                    $events['started'] = $event;
                },
            ],
            ValidationFinishedEvent::class => [
                function ($event) use (&$events) {
                    $events['finished'] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/users');

        $operation = $validator->validateRequest($request);

        $this->assertArrayHasKey('started', $events);
        $this->assertArrayHasKey('finished', $events);
        $this->assertSame('/users', $events['started']->path);
        $this->assertSame('GET', $events['started']->method);
        $this->assertTrue($events['finished']->success);
    }

    #[Test]
    public function dispatches_events_on_validation_failure(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
              required:
                - name
      responses:
        '201':
          description: Created
YAML;

        $events = [];
        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function ($event) use (&$events) {
                    $events['started'] = $event;
                },
            ],
            ValidationFinishedEvent::class => [
                function ($event) use (&$events) {
                    $events['finished'] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = (new Psr17Factory())
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody((new Psr17Factory())->createStream('{"missing": "field"}'));

        try {
            $validator->validateRequest($request);
            $this->fail('Expected validation exception to be thrown');
        } catch (Exception $e) {
            $this->assertArrayHasKey('started', $events);
            $this->assertArrayHasKey('finished', $events);
            $this->assertFalse($events['finished']->success);
        }
    }
}
