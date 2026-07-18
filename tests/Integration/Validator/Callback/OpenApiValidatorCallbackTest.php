<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Callback;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
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

    private const string QUERY_PATH_PARAMS_CALLBACK_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Callback with query and header parameters
  version: 1.0.0
paths:
  /register:
    post:
      operationId: registerSubscription
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [callbackUrl]
              properties:
                callbackUrl:
                  type: string
      responses:
        '201':
          description: Subscription registered
      callbacks:
        eventCallback:
          '{$request.body#/callbackUrl}':
            post:
              parameters:
                - name: eventId
                  in: query
                  required: true
                  schema:
                    type: string
                    minLength: 1
                - name: status
                  in: query
                  required: true
                  schema:
                    type: string
                    enum: [ok, error]
                - name: X-Request-Id
                  in: header
                  required: true
                  schema:
                    type: string
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required: [payload]
                      properties:
                        payload:
                          type: string
              responses:
                '200':
                  description: Event processed
YAML;

    private const string PATH_TEMPLATE_CALLBACK_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Callback with path template expression
  version: 1.0.0
paths:
  /register:
    post:
      operationId: registerSubscription
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [callbackUrl]
              properties:
                callbackUrl:
                  type: string
      responses:
        '201':
          description: Subscription registered
      callbacks:
        eventCallback:
          '/callbacks/{eventId}':
            post:
              parameters:
                - name: eventId
                  in: path
                  required: true
                  schema:
                    type: string
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required: [payload]
                      properties:
                        payload:
                          type: string
              responses:
                '200':
                  description: Event processed
YAML;

    private const string SUCCESS_ERROR_CALLBACKS_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Multiple callbacks (success / error)
  version: 1.0.0
paths:
  /jobs:
    post:
      operationId: createJob
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [successUrl, errorUrl]
              properties:
                successUrl:
                  type: string
                errorUrl:
                  type: string
      responses:
        '202':
          description: Job accepted
      callbacks:
        onSuccess:
          '{$request.body#/successUrl}':
            post:
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required: [result]
                      properties:
                        result:
                          type: string
              responses:
                '200':
                  description: Success callback processed
        onError:
          '{$request.body#/errorUrl}':
            post:
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required: [errorCode]
                      properties:
                        errorCode:
                          type: integer
              responses:
                '200':
                  description: Error callback processed
YAML;

    private const string INLINE_FIXED_URL_CALLBACK_YAML = <<<'YAML'
openapi: 3.2.0
info:
  title: Inline callback with fixed URL
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
              required: [event]
              properties:
                event:
                  type: string
      responses:
        '201':
          description: Subscription created
      callbacks:
        fixedInlineCallback:
          '/webhook/inline/event':
            post:
              requestBody:
                required: true
                content:
                  application/json:
                    schema:
                      type: object
                      required: [inlineId]
                      properties:
                        inlineId:
                          type: string
              responses:
                '200':
                  description: Inline event processed
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
            ->disableStrictCallbackRuntimeTemplate()
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
            ->disableStrictCallbackRuntimeTemplate()
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
            ->disableStrictCallbackRuntimeTemplate()
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
            ->disableStrictCallbackRuntimeTemplate()
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
            ->disableStrictCallbackRuntimeTemplate()
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
            ->disableStrictCallbackRuntimeTemplate()
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
            ->disableStrictCallbackRuntimeTemplate()
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

    #[Test]
    public function wh_07_callback_with_valid_query_and_header_params_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PATH_PARAMS_CALLBACK_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://client.example.com/events?eventId=evt_123&status=ok')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Request-Id', 'req_abc')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['payload' => 'event-data']),
                ),
            );

        $operation = $validator->validateCallback($request, 'eventCallback');

        $this->assertSame('eventCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_07_callback_with_invalid_query_enum_throws_enum_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PATH_PARAMS_CALLBACK_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://client.example.com/events?eventId=evt_123&status=unknown')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Request-Id', 'req_abc')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['payload' => 'event-data']),
                ),
            );

        $this->expectException(EnumError::class);

        $validator->validateCallback($request, 'eventCallback');
    }

    #[Test]
    public function wh_07_callback_without_required_query_param_throws_missing_parameter(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PATH_PARAMS_CALLBACK_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://client.example.com/events?eventId=evt_123')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Request-Id', 'req_abc')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['payload' => 'event-data']),
                ),
            );

        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('Missing required parameter "status" in query');

        $validator->validateCallback($request, 'eventCallback');
    }

    #[Test]
    public function wh_07_callback_without_required_header_throws_missing_parameter(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::QUERY_PATH_PARAMS_CALLBACK_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://client.example.com/events?eventId=evt_123&status=ok')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['payload' => 'event-data']),
                ),
            );

        $this->expectException(MissingParameterException::class);
        $this->expectExceptionMessage('Missing required parameter "X-Request-Id" in header');

        $validator->validateCallback($request, 'eventCallback');
    }

    #[Test]
    public function wh_07_callback_with_path_template_matches_and_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PATH_TEMPLATE_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/callbacks/evt_123')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['payload' => 'event-data']),
                ),
            );

        $operation = $validator->validateCallback($request, 'eventCallback');

        $this->assertSame('eventCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_07_callback_with_path_template_mismatched_value_throws_unknown_callback(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PATH_TEMPLATE_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/callbacks/evt_123/extra/segments')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['payload' => 'event-data']),
                ),
            );

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'eventCallback');
    }

    #[Test]
    public function wh_08_callback_on_success_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SUCCESS_ERROR_CALLBACKS_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://success.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['result' => 'job-completed']),
                ),
            );

        $operation = $validator->validateCallback($request, 'onSuccess');

        $this->assertSame('onSuccess', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_08_callback_on_error_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SUCCESS_ERROR_CALLBACKS_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://errors.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['errorCode' => 500]),
                ),
            );

        $operation = $validator->validateCallback($request, 'onError');

        $this->assertSame('onError', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_08_callback_on_success_with_error_payload_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SUCCESS_ERROR_CALLBACKS_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://success.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['errorCode' => 500]),
                ),
            );

        $this->expectException(ValidationException::class);

        $validator->validateCallback($request, 'onSuccess');
    }

    #[Test]
    public function wh_08_callback_on_error_with_success_payload_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SUCCESS_ERROR_CALLBACKS_YAML)
            ->disableStrictCallbackRuntimeTemplate()
            ->build();

        $request = $this->factory->createServerRequest('POST', 'https://errors.example.com/hook')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['result' => 'ok']),
                ),
            );

        $this->expectException(ValidationException::class);

        $validator->validateCallback($request, 'onError');
    }

    #[Test]
    public function wh_09_inline_fixed_url_callback_with_matching_path_returns_operation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::INLINE_FIXED_URL_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhook/inline/event')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['inlineId' => 'inline-789']),
                ),
            );

        $operation = $validator->validateCallback($request, 'fixedInlineCallback');

        $this->assertSame('fixedInlineCallback', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function wh_09_inline_fixed_url_callback_with_mismatched_path_throws_unknown_callback(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::INLINE_FIXED_URL_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/wrong/path')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['inlineId' => 'inline-789']),
                ),
            );

        $this->expectException(UnknownCallbackException::class);

        $validator->validateCallback($request, 'fixedInlineCallback');
    }

    #[Test]
    public function wh_09_inline_fixed_url_callback_with_invalid_body_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::INLINE_FIXED_URL_CALLBACK_YAML)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/webhook/inline/event')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->factory->createStream(
                    json_encode(['unexpected' => 'value']),
                ),
            );

        $this->expectException(ValidationException::class);

        $validator->validateCallback($request, 'fixedInlineCallback');
    }
}
