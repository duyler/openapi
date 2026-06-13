<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Callback;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class OpenApiValidatorCallbackTest extends TestCase
{
    private const string CALLBACKS_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Callback Test API
  version: 1.0.0
components:
  callbacks:
    paymentCallback:
      paymentCallback:
        '{$request.body#/callbackUrl}':
          post:
            requestBody:
              required: true
              content:
                application/json:
                  schema:
                    type: object
                    required:
                      - transactionId
                      - amount
                    properties:
                      transactionId:
                        type: string
                      amount:
                        type: number
            responses:
              '200':
                description: Payment callback processed
YAML;

    private const string FIXED_URL_CALLBACK_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Fixed URL Callback API
  version: 1.0.0
components:
  callbacks:
    fixedCallback:
      fixedCallback:
        '/webhook/invoice':
          post:
            requestBody:
              required: true
              content:
                application/json:
                  schema:
                    type: object
                    required:
                      - invoiceId
                    properties:
                      invoiceId:
                        type: string
            responses:
              '200':
                description: Invoice callback processed
YAML;

    private const string INLINE_CALLBACK_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Inline Callback API
  version: 1.0.0
paths:
  /subscriptions:
    post:
      operationId: createSubscription
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - callbackUrl
              properties:
                callbackUrl:
                  type: string
      responses:
        '201':
          description: Subscription created
      callbacks:
        subscriptionEvent:
          '{$request.body#/callbackUrl}':
            post:
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required:
                        - eventType
                      properties:
                        eventType:
                          type: string
              responses:
                '200':
                  description: Event processed
YAML;

    private const string REF_CALLBACK_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Ref Callback API
  version: 1.0.0
components:
  pathItems:
    SharedCallbackPath:
      post:
        requestBody:
          required: true
          content:
            application/json:
              schema:
                type: object
                required:
                  - messageId
                properties:
                  messageId:
                    type: string
        responses:
          '200':
            description: Message processed
  callbacks:
    refCallback:
      refCallback:
        '{$request.body#/url}':
          $ref: '#/components/pathItems/SharedCallbackPath'
YAML;

    private const string MULTIPLE_CALLBACKS_YAML = <<<'YAML'
openapi: 3.1.0
info:
  title: Multiple Callbacks API
  version: 1.0.0
paths:
  /orders:
    post:
      operationId: createOrder
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - billingUrl
                - shippingUrl
              properties:
                billingUrl:
                  type: string
                shippingUrl:
                  type: string
      responses:
        '201':
          description: Order created
      callbacks:
        billingEvent:
          '{$request.body#/billingUrl}':
            post:
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required:
                        - amount
                      properties:
                        amount:
                          type: number
              responses:
                '200':
                  description: Billing event processed
        shippingEvent:
          '{$request.body#/shippingUrl}':
            post:
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required:
                        - trackingNumber
                      properties:
                        trackingNumber:
                          type: string
              responses:
                '200':
                  description: Shipping event processed
YAML;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function validate_callback_with_runtime_expression_matches_any_url(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://client.example.com/payment/webhook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['transactionId' => 'tx-123', 'amount' => 99.99]),
                ),
            );

        $operation = $validator->validateCallback($request, 'paymentCallback');

        $this->assertSame('paymentCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_callback_throws_for_unknown_callback_name(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/callback');

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'nonExistentCallback');
    }

    #[Test]
    public function validate_callback_throws_for_mismatched_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('DELETE', '/payment/callback');

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'paymentCallback');
    }

    #[Test]
    public function validate_callback_throws_for_invalid_body(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/payment/callback')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['transactionId' => 'tx-123']),
                ),
            );

        $this->expectException(ValidationException::class);

        $validator->validateCallback($request, 'paymentCallback');
    }

    #[Test]
    public function validate_fixed_url_callback_matches_exact_path(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::FIXED_URL_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhook/invoice')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['invoiceId' => 'inv-456']),
                ),
            );

        $operation = $validator->validateCallback($request, 'fixedCallback');

        $this->assertSame('fixedCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_fixed_url_callback_throws_when_path_mismatches(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::FIXED_URL_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/wrong/path')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['invoiceId' => 'inv-456']),
                ),
            );

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'fixedCallback');
    }

    #[Test]
    public function validate_inline_callback_resolves_from_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::INLINE_CALLBACK_YAML)
            ->build();

        $callbackRequest = $this->factory->createServerRequest('POST', 'https://client.example.com/events')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['eventType' => 'subscription.created']),
                ),
            );

        $operation = $validator->validateCallback($callbackRequest, 'subscriptionEvent');

        $this->assertSame('subscriptionEvent', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_inline_callback_throws_for_unknown_name(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::INLINE_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/events');

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'unknownCallback');
    }

    #[Test]
    public function validate_callback_resolves_path_item_ref_from_components(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REF_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://example.com/anywhere')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['messageId' => 'msg-789']),
                ),
            );

        $operation = $validator->validateCallback($request, 'refCallback');

        $this->assertSame('refCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function validate_each_callback_independently_when_multiple_defined(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPLE_CALLBACKS_YAML)
            ->build();

        $billingRequest = $this->factory->createServerRequest('POST', 'https://billing.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['amount' => 42.5]),
                ),
            );

        $billingOperation = $validator->validateCallback($billingRequest, 'billingEvent');
        $this->assertSame('billingEvent', $billingOperation->path);
        $this->assertSame('POST', $billingOperation->method);

        $shippingRequest = $this->factory->createServerRequest('POST', 'https://shipping.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['trackingNumber' => 'trk-001']),
                ),
            );

        $shippingOperation = $validator->validateCallback($shippingRequest, 'shippingEvent');
        $this->assertSame('shippingEvent', $shippingOperation->path);
        $this->assertSame('POST', $shippingOperation->method);
    }

    #[Test]
    public function validate_billing_callback_throws_when_shipping_payload_used(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::MULTIPLE_CALLBACKS_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://billing.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['trackingNumber' => 'trk-001']),
                ),
            );

        $this->expectException(ValidationException::class);

        $validator->validateCallback($request, 'billingEvent');
    }
}
