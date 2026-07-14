<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E\OpenApi32;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * OA-06: OpenAPI 3.2 webhook with combined 3.2 features (jsonSchemaDialect, $self,
 * recursive schema references in webhook body).
 */
final class Webhook32Test extends TestCase
{
    private const string WEBHOOK_WITH_DATE_TIME_FORMAT_SPEC = <<<'YAML'
openapi: 3.2.0
jsonSchemaDialect: https://json-schema.org/draft/2020-12/schema
info:
  title: Webhook Dialect API
  version: 1.0.0
paths: {}
webhooks:
  event.triggered:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - timestamp
              properties:
                timestamp:
                  type: string
                  format: date-time
      responses:
        '200':
          description: OK
YAML;

    private const string AUDIT_CHAIN_KITCHEN_SINK_SPEC = <<<'YAML'
openapi: 3.2.0
$self: https://api.example.com/schemas/webhooks.json
jsonSchemaDialect: https://json-schema.org/draft/2020-12/schema
info:
  title: Kitchen Sink Webhook API
  version: 1.0.0
paths: {}
webhooks:
  audit.emitted:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/AuditChain'
      responses:
        '200':
          description: Accepted
components:
  schemas:
    AuditChain:
      type: object
      required:
        - actor
        - action
      properties:
        actor:
          type: string
        action:
          type: string
        parent:
          $ref: '#/components/schemas/AuditChain'
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function webhook_3_2_with_dialect_and_self_validates_request(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
\$self: https://api.example.com/schemas/webhooks.json
jsonSchemaDialect: https://json-schema.org/draft/2020-12/schema
info:
  title: Webhook 3.2 API
  version: 1.0.0
paths: {}
webhooks:
  payment.completed:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              \$ref: '#/components/schemas/PaymentEvent'
      responses:
        '200':
          description: Acknowledged
components:
  schemas:
    PaymentEvent:
      type: object
      required:
        - eventId
        - amount
      properties:
        eventId:
          type: string
        amount:
          type: number
          minimum: 0
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream('{"eventId":"evt_1","amount":42.5}'),
            );

        $operation = $validator->validateWebhook($request, 'payment.completed');

        self::assertSame('payment.completed', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function webhook_3_2_with_recursive_schema_in_body_validates_without_loop(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
\$self: https://api.example.com/schemas/webhooks.json
info:
  title: Recursive Webhook API
  version: 1.0.0
paths: {}
webhooks:
  tree.updated:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              \$ref: '#/components/schemas/TreeNode'
      responses:
        '200':
          description: Updated
components:
  schemas:
    TreeNode:
      type: object
      required:
        - id
      properties:
        id:
          type: string
        children:
          type: array
          items:
            \$ref: '#/components/schemas/TreeNode'
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $body = [
            'id' => 'root',
            'children' => [
                ['id' => 'child', 'children' => []],
            ],
        ];

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream((string) json_encode($body, JSON_THROW_ON_ERROR)));

        $operation = $validator->validateWebhook($request, 'tree.updated');

        self::assertSame('tree.updated', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function webhook_3_2_invalid_body_throws_validation_exception(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
\$self: https://api.example.com/schemas/webhooks.json
jsonSchemaDialect: https://json-schema.org/draft/2020-12/schema
info:
  title: Webhook Validation API
  version: 1.0.0
paths: {}
webhooks:
  order.created:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              \$ref: '#/components/schemas/Order'
      responses:
        '200':
          description: OK
components:
  schemas:
    Order:
      type: object
      required:
        - orderId
        - status
      properties:
        orderId:
          type: string
        status:
          type: string
          enum:
            - pending
            - confirmed
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream('{"orderId":"ord_1","status":"unknown"}'));

        $caught = null;

        try {
            $validator->validateWebhook($request, 'order.created');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        }

        self::assertNotNull($caught, 'Invalid enum value must be rejected');
        self::assertInstanceOf(EnumError::class, $caught);
        self::assertSame('enum', $caught->keyword());
        self::assertSame('unknown', $caught->params()['actual']);
    }

    #[Test]
    public function webhook_unknown_name_throws_unknown_webhook_exception(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
\$self: https://api.example.com/schemas/webhooks.json
info:
  title: Webhook API
  version: 1.0.0
paths: {}
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

        $caught = null;

        try {
            $validator->validateWebhook($request, 'unknown.webhook');
        } catch (UnknownWebhookException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Unknown webhook name must throw');
        self::assertStringContainsString('unknown.webhook', $caught->getMessage());
    }

    #[Test]
    public function webhook_3_2_with_dialect_validates_against_standard_rules(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WEBHOOK_WITH_DATE_TIME_FORMAT_SPEC)
            ->build();

        $body = ['timestamp' => '2026-06-16T10:30:00Z'];

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream((string) json_encode($body, JSON_THROW_ON_ERROR)));

        $operation = $validator->validateWebhook($request, 'event.triggered');

        self::assertSame('event.triggered', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function webhook_3_2_with_invalid_date_time_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WEBHOOK_WITH_DATE_TIME_FORMAT_SPEC)
            ->build();

        $body = ['timestamp' => 'not-a-datetime'];

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream((string) json_encode($body, JSON_THROW_ON_ERROR)));

        $caught = null;

        try {
            $validator->validateWebhook($request, 'event.triggered');
        } catch (InvalidFormatException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Invalid date-time must be rejected');
        self::assertSame('format', $caught->keyword());
        self::assertSame('date-time', $caught->params()['format']);
    }

    #[Test]
    public function webhook_3_2_request_with_missing_required_body_throws(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
\$self: https://api.example.com/schemas/webhooks.json
info:
  title: Required Body Webhook API
  version: 1.0.0
paths: {}
webhooks:
  data.changed:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - delta
              properties:
                delta:
                  type: integer
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json');

        $caught = null;

        try {
            $validator->validateWebhook($request, 'data.changed');
        } catch (MissingRequestBodyException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Missing required body must be rejected');
        self::assertStringContainsString('body', $caught->getMessage());
    }

    #[Test]
    public function webhook_3_2_get_method_validates_and_returns_get_operation(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: GET Webhook API
  version: 1.0.0
paths: {}
webhooks:
  health.check:
    get:
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->factory->createServerRequest('GET', '/webhook');

        $operation = $validator->validateWebhook($request, 'health.check');

        self::assertSame('health.check', $operation->path);
        self::assertSame('GET', $operation->method);
    }

    #[Test]
    public function webhook_3_2_string_representation_is_method_path(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
\$self: https://api.example.com/schemas/webhooks.json
jsonSchemaDialect: https://json-schema.org/draft/2020-12/schema
info:
  title: String Repr Webhook API
  version: 1.0.0
paths: {}
webhooks:
  user.signup:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - email
              properties:
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

        $body = ['email' => 'user@example.com'];

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream((string) json_encode($body, JSON_THROW_ON_ERROR)));

        $operation = $validator->validateWebhook($request, 'user.signup');

        self::assertSame('POST user.signup', (string) $operation);
    }

    #[Test]
    public function webhook_3_2_kitchen_sink_combines_self_dialect_and_recursive_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::AUDIT_CHAIN_KITCHEN_SINK_SPEC)
            ->build();

        $body = [
            'actor' => 'system',
            'action' => 'login',
            'parent' => [
                'actor' => 'admin',
                'action' => 'sudo',
            ],
        ];

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream((string) json_encode($body, JSON_THROW_ON_ERROR)));

        $operation = $validator->validateWebhook($request, 'audit.emitted');

        self::assertSame('audit.emitted', $operation->path);
        self::assertSame('POST', $operation->method);
        self::assertSame('POST audit.emitted', (string) $operation);
    }

    #[Test]
    public function webhook_3_2_kitchen_sink_negative_missing_required_field(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::AUDIT_CHAIN_KITCHEN_SINK_SPEC)
            ->build();

        $body = ['action' => 'login'];

        $request = $this->factory
            ->createServerRequest('POST', '/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream((string) json_encode($body, JSON_THROW_ON_ERROR)));

        $caught = null;

        try {
            $validator->validateWebhook($request, 'audit.emitted');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught, 'Missing required actor must be rejected');
        self::assertSame('required', $caught->getErrors()[0]->keyword());
        self::assertStringContainsString('actor', $caught->getErrors()[0]->message());
    }
}
