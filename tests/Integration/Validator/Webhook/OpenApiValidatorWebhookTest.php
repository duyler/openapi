<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Webhook;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation as ValidatorOperation;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class OpenApiValidatorWebhookTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function validate_webhook_returns_operation(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Webhook API
  version: 1.0.0
webhooks:
  payment.completed:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - payment_id
                - status
              properties:
                payment_id:
                  type: string
                status:
                  type: string
                  enum: [completed, failed]
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream('{"payment_id":"abc123","status":"completed"}'),
            );

        $operation = $validator->validateWebhook($request, 'payment.completed');

        self::assertInstanceOf(ValidatorOperation::class, $operation);
        self::assertSame('payment.completed', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_webhook_throws_for_unknown_webhook(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Webhook API
  version: 1.0.0
webhooks:
  payment.completed:
    post:
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhook');

        $this->expectException(UnknownWebhookException::class);

        $validator->validateWebhook($request, 'unknown.webhook');
    }

    #[Test]
    public function validate_webhook_throws_for_invalid_body(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Webhook API
  version: 1.0.0
webhooks:
  payment.completed:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - payment_id
                - status
              properties:
                payment_id:
                  type: string
                status:
                  type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream('{"payment_id":"abc123"}'),
            );

        $this->expectException(ValidationException::class);

        $validator->validateWebhook($request, 'payment.completed');
    }

    #[Test]
    public function validate_webhook_with_valid_data_passes(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Webhook API
  version: 1.0.0
webhooks:
  user.created:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - user_id
                - email
              properties:
                user_id:
                  type: integer
                email:
                  type: string
                  format: email
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhooks/user')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream('{"user_id":42,"email":"user@example.com"}'),
            );

        $operation = $validator->validateWebhook($request, 'user.created');

        self::assertSame('user.created', $operation->path);
        self::assertSame('POST', $operation->method);
    }
}
