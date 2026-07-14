<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

use function is_string;

final class FormatRegistryEventsLoggerTest extends TestCase
{
    private const string SCHEMA_YAML = <<<YAML
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
                phone:
                  type: string
                  format: phone
              required:
                - name
                - phone
      responses:
        '201':
          description: Created
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: integer
                  phone:
                    type: string
                    format: phone
                required:
                  - id
                  - phone
components:
  schemas:
    User:
      type: object
      properties:
        name:
          type: string
        phone:
          type: string
          format: phone
      required:
        - name
        - phone
YAML;

    #[Test]
    public function custom_format_validator_works_for_request_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->build();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"name":"John","phone":"+1234567890"}'));

        $validator->validateRequest($request);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function custom_format_validator_works_for_response_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":1,"phone":"+1234567890"}'));

        $validator->validateResponse($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function custom_format_validator_works_for_schema_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->build();

        $validator->validateSchema(
            ['name' => 'John', 'phone' => '+1234567890'],
            '#/components/schemas/User',
        );

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function custom_format_validator_rejects_invalid_format_in_response(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":1,"phone":"not-a-phone"}'));

        $this->expectException(InvalidFormatException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function custom_format_validator_rejects_invalid_format_in_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->build();

        $this->expectException(InvalidFormatException::class);
        $validator->validateSchema(
            ['name' => 'John', 'phone' => 'not-a-phone'],
            '#/components/schemas/User',
        );
    }

    #[Test]
    public function validation_started_event_dispatched_for_response(): void
    {
        $dispatched = false;
        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function (ValidationStartedEvent $event) use (&$dispatched): void {
                    $dispatched = true;
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withEventDispatcher($dispatcher)
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":1,"phone":"+1234567890"}'));

        $validator->validateResponse($response, $operation);

        self::assertTrue($dispatched);
    }

    #[Test]
    public function validation_finished_event_dispatched_for_response(): void
    {
        $finished = false;
        $dispatcher = new ArrayDispatcher([
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$finished): void {
                    $finished = true;
                    self::assertTrue($event->success);
                    self::assertSame('/users', $event->path);
                    self::assertSame('POST', $event->method);
                    self::assertGreaterThan(0, $event->duration);
                    self::assertNotNull($event->response);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withEventDispatcher($dispatcher)
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":1,"phone":"+1234567890"}'));

        $validator->validateResponse($response, $operation);

        self::assertTrue($finished);
    }

    #[Test]
    public function validation_error_event_dispatched_for_invalid_response(): void
    {
        $errorDispatched = false;
        $finishedSuccess = null;
        $dispatcher = new ArrayDispatcher([
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$finishedSuccess): void {
                    $finishedSuccess = $event->success;
                },
            ],
            ValidationErrorEvent::class => [
                function (ValidationErrorEvent $event) use (&$errorDispatched): void {
                    $errorDispatched = true;
                    self::assertNotNull($event->exception);
                    self::assertNotNull($event->response);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withEventDispatcher($dispatcher)
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"invalid":"data"}'));

        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException) {
        }

        self::assertFalse($finishedSuccess);
        self::assertTrue($errorDispatched);
    }

    #[Test]
    public function validation_started_event_dispatched_for_schema(): void
    {
        $dispatched = false;
        $dispatcher = new ArrayDispatcher([
            ValidationStartedEvent::class => [
                function (ValidationStartedEvent $event) use (&$dispatched): void {
                    $dispatched = true;
                    self::assertSame('SCHEMA', $event->method);
                    self::assertSame('#/components/schemas/User', $event->schemaRef);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withEventDispatcher($dispatcher)
            ->build();

        $validator->validateSchema(
            ['name' => 'John', 'phone' => '+1234567890'],
            '#/components/schemas/User',
        );

        self::assertTrue($dispatched);
    }

    #[Test]
    public function validation_finished_event_dispatched_for_schema(): void
    {
        $finished = false;
        $dispatcher = new ArrayDispatcher([
            ValidationFinishedEvent::class => [
                function (ValidationFinishedEvent $event) use (&$finished): void {
                    $finished = true;
                    self::assertTrue($event->success);
                    self::assertSame('#/components/schemas/User', $event->schemaRef);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withEventDispatcher($dispatcher)
            ->build();

        $validator->validateSchema(
            ['name' => 'John', 'phone' => '+1234567890'],
            '#/components/schemas/User',
        );

        self::assertTrue($finished);
    }

    #[Test]
    public function validation_error_event_dispatched_for_invalid_schema(): void
    {
        $errorDispatched = false;
        $dispatcher = new ArrayDispatcher([
            ValidationErrorEvent::class => [
                function (ValidationErrorEvent $event) use (&$errorDispatched): void {
                    $errorDispatched = true;
                    self::assertSame('#/components/schemas/User', $event->schemaRef);
                },
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withEventDispatcher($dispatcher)
            ->build();

        try {
            $validator->validateSchema(
                ['invalid' => 'data'],
                '#/components/schemas/User',
            );
        } catch (ValidationException) {
        }

        self::assertTrue($errorDispatched);
    }

    #[Test]
    public function logger_receives_info_calls_during_request_validation(): void
    {
        $logger = new MockLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withLogger($logger)
            ->build();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"name":"John","phone":"+1234567890"}'));

        $validator->validateRequest($request);

        self::assertTrue($logger->hasInfoContaining('Validating request'));
    }

    #[Test]
    public function logger_receives_info_calls_during_response_validation(): void
    {
        $logger = new MockLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withLogger($logger)
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"id":1,"phone":"+1234567890"}'));

        $validator->validateResponse($response, $operation);

        self::assertTrue($logger->hasInfoContaining('Validating response'));
    }

    #[Test]
    public function logger_receives_info_calls_during_schema_validation(): void
    {
        $logger = new MockLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withLogger($logger)
            ->build();

        $validator->validateSchema(
            ['name' => 'John', 'phone' => '+1234567890'],
            '#/components/schemas/User',
        );

        self::assertTrue($logger->hasInfoContaining('Resolving schema ref'));
        self::assertTrue($logger->hasInfoContaining('Validating schema'));
    }

    #[Test]
    public function logger_receives_warning_on_failed_request_validation(): void
    {
        $logger = new MockLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withLogger($logger)
            ->build();

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"invalid":"data"}'));

        try {
            $validator->validateRequest($request);
        } catch (ValidationException) {
        }

        self::assertTrue($logger->hasWarningContaining('Request validation failed'));
    }

    #[Test]
    public function logger_receives_warning_on_failed_response_validation(): void
    {
        $logger = new MockLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withLogger($logger)
            ->build();

        $operation = new Operation('/users', 'POST');
        $factory = new Psr17Factory();
        $response = $factory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"invalid":"data"}'));

        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException) {
        }

        self::assertTrue($logger->hasWarningContaining('Response validation failed'));
    }

    #[Test]
    public function logger_receives_warning_on_failed_schema_validation(): void
    {
        $logger = new MockLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->withLogger($logger)
            ->build();

        try {
            $validator->validateSchema(
                ['invalid' => 'data'],
                '#/components/schemas/User',
            );
        } catch (ValidationException) {
        }

        self::assertTrue($logger->hasWarningContaining('Schema validation failed'));
    }

    #[Test]
    public function validator_works_without_logger(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->withFormat('string', 'phone', new PhoneValidator())
            ->build();

        $validator->validateSchema(
            ['name' => 'John', 'phone' => '+1234567890'],
            '#/components/schemas/User',
        );

        $this->expectNotToPerformAssertions();
    }
}

final class PhoneValidator implements FormatValidatorInterface
{
    public function validate(mixed $data): void
    {
        if (!is_string($data) || !preg_match('/^\+?[1-9]\d{6,14}$/', $data)) {
            throw new InvalidFormatException(
                'phone',
                $data,
                'Value must be a valid E.164 phone number',
            );
        }
    }
}

final class MockLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array<mixed>}> */
    private array $logs = [];

    #[Override]
    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasInfoContaining(string $substring): bool
    {
        return array_any($this->logs, fn($log) => 'info' === $log['level'] && str_contains($log['message'], $substring));
    }

    public function hasWarningContaining(string $substring): bool
    {
        return array_any($this->logs, fn($log) => 'warning' === $log['level'] && str_contains($log['message'], $substring));
    }
}
