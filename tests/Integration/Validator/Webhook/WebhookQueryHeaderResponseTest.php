<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Webhook;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;

/** @internal */
final class WebhookQueryHeaderResponseTest extends TestCase
{
    private const string QUERY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Webhook Query API
  version: 1.0.0
webhooks:
  payment.completed:
    post:
      operationId: paymentCompleted
      parameters:
        - name: page
          in: query
          required: false
          schema:
            type: integer
            minimum: 1
        - name: status
          in: query
          required: true
          schema:
            type: string
            enum: [success, failed, pending]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [paymentId]
              properties:
                paymentId:
                  type: string
      responses:
        '200':
          description: Acknowledged
YAML;

    private const string HEADER_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Webhook Header API
  version: 1.0.0
webhooks:
  payment.event:
    post:
      operationId: paymentEvent
      parameters:
        - name: X-Signature
          in: header
          required: true
          schema:
            type: string
            minLength: 1
        - name: X-Event-Type
          in: header
          required: true
          schema:
            type: string
            enum: [payment.completed, payment.failed]
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [eventId]
              properties:
                eventId:
                  type: string
      responses:
        '200':
          description: Acknowledged
YAML;

    private const string FULL_CYCLE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Webhook Full Cycle API
  version: 1.0.0
webhooks:
  order.completed:
    post:
      operationId: orderCompleted
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [orderId]
              properties:
                orderId:
                  type: string
      responses:
        '200':
          description: Acknowledged
          content:
            application/json:
              schema:
                type: object
                required: [acknowledged, requestId]
                properties:
                  acknowledged:
                    type: boolean
                  requestId:
                    type: string
        '400':
          description: Bad request
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function wh_01_webhook_with_valid_query_parameters_returns_operation(): void
    {
        $validator = $this->buildQueryValidator();

        $request = $this->createWebhookRequest(
            '/webhook?status=success&page=2',
            '{"paymentId":"pay_123"}',
        );

        $operation = $validator->validateWebhook($request, 'payment.completed');

        self::assertSame('payment.completed', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_01_webhook_with_only_required_query_parameter_returns_operation(): void
    {
        $validator = $this->buildQueryValidator();

        $request = $this->createWebhookRequest(
            '/webhook?status=pending',
            '{"paymentId":"pay_456"}',
        );

        $operation = $validator->validateWebhook($request, 'payment.completed');

        self::assertSame('payment.completed', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_01_webhook_without_required_query_parameter_throws_missing_parameter(): void
    {
        $validator = $this->buildQueryValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"paymentId":"pay_123"}',
        );

        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('Missing required parameter "status" in query');

        $validator->validateWebhook($request, 'payment.completed');
    }

    #[Test]
    public function wh_01_webhook_with_invalid_enum_query_parameter_throws_enum_error(): void
    {
        $validator = $this->buildQueryValidator();

        $request = $this->createWebhookRequest(
            '/webhook?status=unknown',
            '{"paymentId":"pay_123"}',
        );

        $this->expectException(EnumError::class);

        $validator->validateWebhook($request, 'payment.completed');
    }

    #[Test]
    public function wh_01_webhook_with_page_below_minimum_throws_minimum_error(): void
    {
        $validator = $this->buildQueryValidator();

        $request = $this->createWebhookRequest(
            '/webhook?status=success&page=0',
            '{"paymentId":"pay_123"}',
        );

        $this->expectException(MinimumError::class);

        $validator->validateWebhook($request, 'payment.completed');
    }

    #[Test]
    public function wh_02_webhook_with_required_headers_returns_operation(): void
    {
        $validator = $this->buildHeaderValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"eventId":"evt_123"}',
            ['X-Signature' => 'abc123def456', 'X-Event-Type' => 'payment.completed'],
        );

        $operation = $validator->validateWebhook($request, 'payment.event');

        self::assertSame('payment.event', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_02_webhook_without_required_header_throws_missing_parameter(): void
    {
        $validator = $this->buildHeaderValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"eventId":"evt_123"}',
            ['X-Event-Type' => 'payment.completed'],
        );

        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('Missing required parameter "X-Signature" in header');

        $validator->validateWebhook($request, 'payment.event');
    }

    #[Test]
    public function wh_02_webhook_with_invalid_header_enum_throws_enum_error(): void
    {
        $validator = $this->buildHeaderValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"eventId":"evt_123"}',
            ['X-Signature' => 'abc123', 'X-Event-Type' => 'invalid.type'],
        );

        $this->expectException(EnumError::class);

        $validator->validateWebhook($request, 'payment.event');
    }

    #[Test]
    public function wh_03_webhook_to_response_full_cycle_passes(): void
    {
        $validator = $this->buildFullCycleValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"orderId":"ord_123"}',
        );

        $operation = $validator->validateWebhook($request, 'order.completed');

        self::assertSame('order.completed', $operation->path);
        self::assertSame('POST', $operation->method);

        $response = $this->createJsonResponse(
            200,
            '{"acknowledged":true,"requestId":"req_abc"}',
        );

        $this->assertResponsePasses($validator, $response, $operation);
    }

    #[Test]
    public function wh_03_webhook_to_response_with_undefined_status_throws(): void
    {
        $validator = $this->buildFullCycleValidator();

        $operation = $this->validateOrderWebhook($validator);

        $response = $this->createJsonResponse(
            500,
            '{"error":"internal server error"}',
        );

        $this->expectException(UndefinedResponseException::class);

        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function wh_03_webhook_to_response_with_invalid_body_throws_validation_exception(): void
    {
        $validator = $this->buildFullCycleValidator();

        $operation = $this->validateOrderWebhook($validator);

        $response = $this->createJsonResponse(
            200,
            '{"wrongField":true}',
        );

        $error = $this->catchResponseError($validator, $response, $operation);

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('acknowledged', $error->getMessage());
    }

    #[Test]
    public function wh_03_webhook_to_response_with_missing_required_field_throws_validation_exception(): void
    {
        $validator = $this->buildFullCycleValidator();

        $operation = $this->validateOrderWebhook($validator);

        $response = $this->createJsonResponse(
            200,
            '{"acknowledged":true}',
        );

        $error = $this->catchResponseError($validator, $response, $operation);

        self::assertInstanceOf(ValidationException::class, $error);
        self::assertStringContainsString('requestId', $error->getMessage());
    }

    private function buildQueryValidator(): OpenApiValidatorInterface
    {
        return $this->buildValidator(self::QUERY_SPEC, coercion: true);
    }

    private function buildHeaderValidator(): OpenApiValidatorInterface
    {
        return $this->buildValidator(self::HEADER_SPEC);
    }

    private function buildFullCycleValidator(): OpenApiValidatorInterface
    {
        return $this->buildValidator(self::FULL_CYCLE_SPEC);
    }

    private function buildValidator(string $yaml, bool $coercion = false): OpenApiValidatorInterface
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString($yaml);

        if ($coercion) {
            $builder = $builder->enableCoercion();
        }

        return $builder->build();
    }

    // Helper duplicated in WebhookSecurityCoercionTest for test isolation.
    /**
     * @param array<string, string> $headers
     */
    private function createWebhookRequest(
        string $uri,
        string $body,
        array $headers = ['Content-Type' => 'application/json'],
    ): ServerRequestInterface {
        $request = $this->factory->createServerRequest('POST', $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if (!isset($headers['Content-Type'])) {
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        return $request->withBody($this->factory->createStream($body));
    }

    private function createJsonResponse(int $status, string $body): ResponseInterface
    {
        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }

    private function validateOrderWebhook(OpenApiValidatorInterface $validator): Operation
    {
        $request = $this->createWebhookRequest(
            '/webhook',
            '{"orderId":"ord_123"}',
        );

        return $validator->validateWebhook($request, 'order.completed');
    }

    private function assertResponsePasses(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): void {
        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException $e) {
            self::fail(sprintf(
                'Expected response validation to pass, but %s was thrown: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::addToAssertionCount(1);
    }

    private function catchResponseError(
        OpenApiValidatorInterface $validator,
        ResponseInterface $response,
        Operation $operation,
    ): ValidationException {
        try {
            $validator->validateResponse($response, $operation);
        } catch (ValidationException $e) {
            return $e;
        }

        self::fail('Expected a ValidationException, but response validation passed');
    }
}
