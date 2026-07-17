<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Exception;

/** @internal */
final class OpenApiValidatorSchemaTest extends TestCase
{
    private OpenApiValidator $validator;
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: List users
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
      responses:
        '200':
          description: Success
components:
  schemas:
    User:
      type: object
      properties:
        name:
          type: string
        age:
          type: integer
      required:
        - name
    PositiveInt:
      type: integer
      minimum: 0
YAML;

        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function validate_schema_with_valid_data(): void
    {
        $data = ['name' => 'John', 'age' => 30];

        $this->validator->validateSchema($data, '#/components/schemas/User');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_schema_throws_for_invalid_data(): void
    {
        $data = ['age' => 30];

        $this->expectException(ValidationException::class);

        $this->validator->validateSchema($data, '#/components/schemas/User');
    }

    #[Test]
    public function validate_schema_throws_for_type_mismatch(): void
    {
        $data = ['name' => 123];

        $caught = null;

        try {
            $this->validator->validateSchema($data, '#/components/schemas/User');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $caught = $errors[0] ?? null;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
    }

    #[Test]
    public function validate_schema_throws_for_invalid_ref(): void
    {
        $this->expectException(Exception::class);

        $this->validator->validateSchema(['test' => 1], '#/components/schemas/NonExistent');
    }

    #[Test]
    public function validate_schema_with_integer_constraint(): void
    {
        $this->validator->validateSchema(5, '#/components/schemas/PositiveInt');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_schema_with_integer_constraint_fails(): void
    {
        $this->expectException(ValidationException::class);

        $this->validator->validateSchema(-1, '#/components/schemas/PositiveInt');
    }

    #[Test]
    public function get_formatted_errors_returns_string(): void
    {
        try {
            $this->validator->validateSchema(['age' => 30], '#/components/schemas/User');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $formatted = $this->validator->getFormattedErrors($e);

            self::assertIsString($formatted);
            self::assertNotEmpty($formatted);
        }
    }

    #[Test]
    public function validate_webhook_with_valid_request(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
webhooks:
  payment.webhook:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                event:
                  type: string
              required:
                - event
      responses:
        '200':
          description: Success
YAML;

        $webhookValidator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"event":"payment.success"}'));

        $operation = $webhookValidator->validateWebhook($request, 'payment.webhook');

        self::assertInstanceOf(Operation::class, $operation);
        self::assertSame('payment.webhook', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_webhook_throws_for_unknown_webhook(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
YAML;

        $webhookValidator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/');

        $this->expectException(UnknownWebhookException::class);

        $webhookValidator->validateWebhook($request, 'unknown.webhook');
    }

    #[Test]
    public function validate_webhook_throws_for_invalid_body(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
webhooks:
  payment.webhook:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - event
      responses:
        '200':
          description: Success
YAML;

        $webhookValidator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"wrong":"field"}'));

        $this->expectException(ValidationException::class);

        $webhookValidator->validateWebhook($request, 'payment.webhook');
    }

    #[Test]
    public function validate_schema_with_event_dispatcher(): void
    {
        $dispatchedEvents = [];

        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function (ValidationStartedEvent $event) use (&$dispatchedEvents): void {
                    $dispatchedEvents[] = 'started: ' . $event->method . ' ' . $event->path;
                },
            ],
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$dispatchedEvents): void {
                    $dispatchedEvents[] = 'finished: ' . ($event->success ? 'ok' : 'fail');
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString("openapi: 3.2.0\ninfo:\n  title: Test\n  version: 1.0.0\ncomponents:\n  schemas:\n    Test:\n      type: string")
            ->withEventDispatcher($dispatcher)
            ->build();

        $validator->validateSchema('hello', '#/components/schemas/Test');

        self::assertCount(2, $dispatchedEvents);
        self::assertStringContainsString('started: SCHEMA', $dispatchedEvents[0]);
        self::assertStringContainsString('finished: ok', $dispatchedEvents[1]);
    }
}
