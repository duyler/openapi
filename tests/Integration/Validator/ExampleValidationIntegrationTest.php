<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExampleValidationIntegrationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function dispatches_warning_when_response_body_does_not_match_example(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                required:
                  - name
                example:
                  name: Alice
YAML;

        $warnings = [];
        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                json_encode(['name' => 'Bob']),
            ));

        $validator->validateResponse($response, $operation);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('does not match', $warnings[0]->message);
        $this->assertStringContainsString('{"name":"Bob"}', $warnings[0]->message);
    }

    #[Test]
    public function does_not_dispatch_warning_when_response_body_matches_example(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                required:
                  - name
                example:
                  name: Alice
YAML;

        $warnings = [];
        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                json_encode(['name' => 'Alice']),
            ));

        $validator->validateResponse($response, $operation);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function dispatches_warning_when_request_body_does_not_match_example(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
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
              example:
                name: Alice
      responses:
        '201':
          description: Created
YAML;

        $warnings = [];
        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                json_encode(['name' => 'Charlie']),
            ));

        $validator->validateRequest($request);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('does not match', $warnings[0]->message);
        $this->assertStringContainsString('{"name":"Charlie"}', $warnings[0]->message);
    }

    #[Test]
    public function does_not_dispatch_warning_when_request_body_matches_example(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
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
              example:
                name: Alice
      responses:
        '201':
          description: Created
YAML;

        $warnings = [];
        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                json_encode(['name' => 'Alice']),
            ));

        $validator->validateRequest($request);

        $this->assertSame([], $warnings);
    }

    #[Test]
    public function does_not_dispatch_warning_when_no_event_dispatcher_configured(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  name:
                    type: string
                required:
                  - name
                example:
                  name: Alice
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                json_encode(['name' => 'Bob']),
            ));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function dispatches_warning_for_media_type_scalar_example_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /health:
    get:
      responses:
        '200':
          description: Success
          content:
            text/plain:
              schema:
                type: string
              example: ok
YAML;

        $warnings = [];
        $dispatcher = new ArrayDispatcher([
            ValidationWarningEvent::class => [
                function (ValidationWarningEvent $event) use (&$warnings): void {
                    $warnings[] = $event;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withEventDispatcher($dispatcher)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/health');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->psrFactory->createStream('fail'));

        $validator->validateResponse($response, $operation);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('does not match', $warnings[0]->message);
    }
}
