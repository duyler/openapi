<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Webhook;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Covers webhook edge cases:
 * - WH-04: webhook with security scheme (http/bearer)
 * - WH-05: webhook with type coercion
 *
 * @internal
 */
final class WebhookSecurityCoercionTest extends TestCase
{
    private const string SECURITY_BEARER_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Webhook Security API
  version: 1.0.0
security:
  - bearerAuth: []
webhooks:
  payment.event:
    post:
      operationId: paymentEvent
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
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
YAML;

    private const string COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Webhook Coercion API
  version: 1.0.0
webhooks:
  order.event:
    post:
      operationId: orderEvent
      parameters:
        - name: version
          in: query
          required: true
          schema:
            type: integer
            minimum: 1
        - name: amount
          in: query
          required: true
          schema:
            type: number
            minimum: 0
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
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function wh_04_webhook_with_valid_bearer_token_returns_operation(): void
    {
        $validator = $this->buildSecurityValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"eventId":"evt_123"}',
            ['Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test'],
        );

        $operation = $validator->validateWebhook($request, 'payment.event');

        self::assertSame('payment.event', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    /**
     * When enableSecurityValidation() is active and the spec declares a
     * security scheme for a webhook, a request missing the required
     * credentials must be rejected with a ValidationException.
     */
    #[Test]
    public function wh_04_webhook_without_authorization_throws_security_exception(): void
    {
        $validator = $this->buildSecurityValidator();

        $request = $this->createWebhookRequest(
            '/webhook',
            '{"eventId":"evt_456"}',
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Security validation failed for POST payment.event');

        $validator->validateWebhook($request, 'payment.event');
    }

    #[Test]
    public function wh_05_webhook_with_integer_query_coerced_from_string_passes(): void
    {
        $validator = $this->buildCoercionValidator();

        $request = $this->createWebhookRequest(
            '/webhook?version=42&amount=99.5',
            '{"orderId":"ord_123"}',
        );

        $operation = $validator->validateWebhook($request, 'order.event');

        self::assertSame('order.event', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_05_webhook_with_non_coercible_integer_query_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator();

        $request = $this->createWebhookRequest(
            '/webhook?version=abc&amount=99.5',
            '{"orderId":"ord_123"}',
        );

        $this->expectException(TypeMismatchError::class);

        $validator->validateWebhook($request, 'order.event');
    }

    #[Test]
    public function wh_05_webhook_with_coerced_integer_below_minimum_throws_minimum_error(): void
    {
        $validator = $this->buildCoercionValidator();

        $request = $this->createWebhookRequest(
            '/webhook?version=0&amount=99.5',
            '{"orderId":"ord_123"}',
        );

        $this->expectException(MinimumError::class);

        $validator->validateWebhook($request, 'order.event');
    }

    #[Test]
    public function wh_05_webhook_with_coerced_number_below_minimum_throws_minimum_error(): void
    {
        $validator = $this->buildCoercionValidator();

        $request = $this->createWebhookRequest(
            '/webhook?version=2&amount=-5',
            '{"orderId":"ord_123"}',
        );

        $this->expectException(MinimumError::class);

        $validator->validateWebhook($request, 'order.event');
    }

    #[Test]
    public function wh_05_webhook_with_coercion_boundary_value_passes(): void
    {
        $validator = $this->buildCoercionValidator();

        $request = $this->createWebhookRequest(
            '/webhook?version=1&amount=0',
            '{"orderId":"ord_456"}',
        );

        $operation = $validator->validateWebhook($request, 'order.event');

        self::assertSame('order.event', $operation->path);
        self::assertSame('POST', $operation->method);
    }

    private function buildSecurityValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SECURITY_BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();
    }

    private function buildCoercionValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COERCION_SPEC)
            ->enableCoercion()
            ->build();
    }

    // Helper duplicated in WebhookQueryHeaderResponseTest for test isolation.
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
}
