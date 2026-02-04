<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Webhook;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\HeadersValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\QueryParametersValidator;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\RequestBodyValidator;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Duyler\OpenApi\Validator\Webhook\WebhookValidator;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use stdClass;

/** @internal */
final class WebhookValidatorTest extends TestCase
{
    private WebhookValidator $webhookValidator;

    protected function setUp(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool);
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $pathParser = new PathParser();
        $pathParamsValidator = new PathParametersValidator($schemaValidator, $deserializer, $coercer);
        $queryParser = new QueryParser();
        $queryParamsValidator = new QueryParametersValidator($schemaValidator, $deserializer, $coercer);
        $headersValidator = new HeadersValidator($schemaValidator, $coercer);
        $cookieValidator = new CookieValidator($schemaValidator, $deserializer, $coercer);
        $negotiator = new ContentTypeNegotiator();
        $jsonParser = new JsonBodyParser();
        $formParser = new FormBodyParser();
        $multipartParser = new MultipartBodyParser();
        $textParser = new TextBodyParser();
        $xmlParser = new XmlBodyParser();
        $bodyValidator = new RequestBodyValidator(
            $schemaValidator,
            $negotiator,
            $jsonParser,
            $formParser,
            $multipartParser,
            $textParser,
            $xmlParser,
        );

        $requestValidator = new RequestValidator(
            $pathParser,
            $pathParamsValidator,
            $queryParser,
            $queryParamsValidator,
            $headersValidator,
            $cookieValidator,
            $bodyValidator,
        );

        $this->webhookValidator = new WebhookValidator($requestValidator);
    }

    #[Test]
    public function unknown_webhook_exception_has_correct_message(): void
    {
        $exception = new UnknownWebhookException('test_webhook');

        self::assertSame('Unknown webhook: test_webhook', $exception->getMessage());
    }

    #[Test]
    public function openapi_document_can_have_webhooks(): void
    {
        $operation = new Operation();
        $pathItem = new PathItem(post: $operation);

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(
                title: 'Test API',
                version: '1.0.0',
            ),
            webhooks: new Webhooks(['test_webhook' => $pathItem]),
        );

        self::assertNotNull($document->webhooks);
        self::assertIsArray($document->webhooks->webhooks);
        self::assertArrayHasKey('test_webhook', $document->webhooks->webhooks);
    }

    #[Test]
    public function webhook_validator_class_exists(): void
    {
        self::assertTrue(class_exists(WebhookValidator::class));
    }

    #[Test]
    public function validate_valid_webhook_request(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: '{"payment_id":"123e4567-e89b-12d3-a456-426614174000","status":"completed","amount":99.99}',
            webhookName: 'payment.updated',
        );

        $document = $this->createWebhookDocument();

        $this->webhookValidator->validate($request, 'payment.updated', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_throws_for_unknown_webhook(): void
    {
        $request = $this->createPsr7RequestForWebhook(method: 'POST', webhookName: 'unknown.webhook');

        $document = $this->createWebhookDocument();

        $this->expectException(UnknownWebhookException::class);
        $this->expectExceptionMessage('Unknown webhook: unknown.webhook');

        $this->webhookValidator->validate($request, 'unknown.webhook', $document);
    }

    #[Test]
    public function validate_throws_for_invalid_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(method: 'GET', webhookName: 'payment.updated');

        $document = $this->createWebhookDocument();

        $this->expectException(UnknownWebhookException::class);

        $this->webhookValidator->validate($request, 'payment.updated', $document);
    }

    #[Test]
    public function validate_with_different_webhooks(): void
    {
        $request1 = $this->createPsr7RequestForWebhook(
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: '{"payment_id":"123","status":"pending","amount":50}',
            webhookName: 'payment.updated',
        );

        $request2 = $this->createPsr7RequestForWebhook(
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: '{"subscription_id":"sub_123","user_id":456,"renewed_at":"2024-01-01T00:00:00Z"}',
            webhookName: 'subscription.renewed',
        );

        $document = $this->createWebhookDocument();

        $this->webhookValidator->validate($request1, 'payment.updated', $document);
        $this->webhookValidator->validate($request2, 'subscription.renewed', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_throws_for_missing_required_field(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'POST',
            headers: ['Content-Type' => 'application/json'],
            body: '{"payment_id":"123","status":"completed"}',
            webhookName: 'payment.updated',
        );

        $document = $this->createWebhookDocument();

        $this->expectException(Exception::class);

        $this->webhookValidator->validate($request, 'payment.updated', $document);
    }

    #[Test]
    public function validate_with_query_parameters(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'POST',
            queryParams: ['verify' => 'true'],
            headers: ['Content-Type' => 'application/json'],
            body: '{"payment_id":"123","status":"completed","amount":50}',
            webhookName: 'payment.updated',
        );

        $operation = new Operation(
            parameters: new Parameters([
                new Parameter(
                    name: 'verify',
                    in: 'query',
                    schema: new Schema(type: 'string'),
                ),
            ]),
            requestBody: new RequestBody(
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            required: ['payment_id', 'status', 'amount'],
                            properties: [
                                'payment_id' => new Schema(type: 'string'),
                                'status' => new Schema(type: 'string'),
                                'amount' => new Schema(type: 'number'),
                            ],
                        ),
                    ),
                ]),
            ),
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.updated' => new PathItem(post: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.updated', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_headers(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'POST',
            headers: [
                'Content-Type' => 'application/json',
                'X-Webhook-Signature' => 'signature123',
            ],
            body: '{"payment_id":"123","status":"completed","amount":50}',
            webhookName: 'payment.updated',
        );

        $operation = new Operation(
            parameters: new Parameters([
                new Parameter(
                    name: 'X-Webhook-Signature',
                    in: 'header',
                    required: true,
                    schema: new Schema(type: 'string'),
                ),
            ]),
            requestBody: new RequestBody(
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            required: ['payment_id', 'status', 'amount'],
                            properties: [
                                'payment_id' => new Schema(type: 'string'),
                                'status' => new Schema(type: 'string'),
                                'amount' => new Schema(type: 'number'),
                            ],
                        ),
                    ),
                ]),
            ),
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.updated' => new PathItem(post: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.updated', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_put_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'PUT',
            headers: ['Content-Type' => 'application/json'],
            body: '{"payment_id":"123","status":"updated","amount":75}',
            webhookName: 'payment.updated',
        );

        $operation = new Operation(
            requestBody: new RequestBody(
                required: true,
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            required: ['payment_id', 'status', 'amount'],
                            properties: [
                                'payment_id' => new Schema(type: 'string'),
                                'status' => new Schema(type: 'string'),
                                'amount' => new Schema(type: 'number'),
                            ],
                        ),
                    ),
                ]),
            ),
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.updated' => new PathItem(put: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.updated', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_patch_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'PATCH',
            headers: ['Content-Type' => 'application/json'],
            body: '{"payment_id":"123","status":"partial"}',
            webhookName: 'payment.updated',
        );

        $operation = new Operation(
            requestBody: new RequestBody(
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            properties: [
                                'payment_id' => new Schema(type: 'string'),
                                'status' => new Schema(type: 'string'),
                            ],
                        ),
                    ),
                ]),
            ),
        );

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.updated' => new PathItem(patch: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.updated', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_delete_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'DELETE',
            webhookName: 'payment.deleted',
        );

        $operation = new Operation();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.deleted' => new PathItem(delete: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.deleted', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_options_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'OPTIONS',
            webhookName: 'payment.options',
        );

        $operation = new Operation();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.options' => new PathItem(options: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.options', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_head_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'HEAD',
            webhookName: 'payment.head',
        );

        $operation = new Operation();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.head' => new PathItem(head: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.head', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_trace_method(): void
    {
        $request = $this->createPsr7RequestForWebhook(
            method: 'TRACE',
            webhookName: 'payment.trace',
        );

        $operation = new Operation();

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.trace' => new PathItem(trace: $operation),
            ]),
        );

        $this->webhookValidator->validate($request, 'payment.trace', $document);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_throws_for_invalid_operation_type(): void
    {
        $pathItem = new class {
            public object $post;

            public function __construct()
            {
                $this->post = new stdClass();
            }
        };

        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            webhooks: new Webhooks([
                'test.webhook' => $pathItem,
            ]),
        );

        $request = $this->createPsr7RequestForWebhook(method: 'POST', webhookName: 'test.webhook');

        $this->expectException(UnknownWebhookException::class);
        $this->expectExceptionMessage('test.webhook (invalid operation)');

        $this->webhookValidator->validate($request, 'test.webhook', $document);
    }

    private function createWebhookDocument(): OpenApiDocument
    {
        $paymentOperation = new Operation(
            requestBody: new RequestBody(
                required: true,
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            properties: [
                                'payment_id' => new Schema(type: 'string'),
                                'status' => new Schema(
                                    type: 'string',
                                    enum: ['pending', 'completed', 'failed'],
                                ),
                                'amount' => new Schema(type: 'number', minimum: 0),
                            ],
                            required: ['payment_id', 'status', 'amount'],
                        ),
                    ),
                ]),
            ),
            responses: new Responses(['200' => new Response(description: 'OK')]),
        );

        $subscriptionOperation = new Operation(
            requestBody: new RequestBody(
                required: true,
                content: new Content([
                    'application/json' => new MediaType(
                        schema: new Schema(
                            type: 'object',
                            properties: [
                                'subscription_id' => new Schema(type: 'string'),
                                'user_id' => new Schema(type: 'integer'),
                                'renewed_at' => new Schema(type: 'string', format: 'date-time'),
                            ],
                            required: ['subscription_id', 'user_id'],
                        ),
                    ),
                ]),
            ),
            responses: new Responses(['200' => new Response(description: 'OK')]),
        );

        return new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Webhook API', version: '1.0.0'),
            webhooks: new Webhooks([
                'payment.updated' => new PathItem(post: $paymentOperation),
                'subscription.renewed' => new PathItem(post: $subscriptionOperation),
            ]),
        );
    }

    private function createPsr7Request(
        string $method,
        string $uri,
        array $queryParams = [],
        array $headers = [],
        array $cookies = [],
        string $body = '',
        string $contentType = 'application/json',
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);

        // For webhooks, the URI should match the webhook name (used as path template)
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn($uri);
        $uriMock->method('getQuery')->willReturn(http_build_query($queryParams));

        $request->method('getUri')->willReturn($uriMock);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getHeaders')->willReturn($headers);
        $request->method('getHeaderLine')->willReturnMap([
            ['Cookie', http_build_query($cookies, '', '; ')],
            ['Content-Type', $contentType],
        ]);
        $request->method('getCookieParams')->willReturn($cookies);

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($body);
        $request->method('getBody')->willReturn($bodyMock);

        return $request;
    }

    private function createPsr7RequestForWebhook(
        string $method,
        array $queryParams = [],
        array $headers = [],
        array $cookies = [],
        string $body = '',
        string $contentType = 'application/json',
        string $webhookName = '',
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);

        // Use webhook name as path to match the template passed to RequestValidator
        // WebhookValidator passes webhook name (with dots) as path template
        $path = '' !== $webhookName ? $webhookName : 'webhook';
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn($path);
        $uriMock->method('getQuery')->willReturn(http_build_query($queryParams));

        $request->method('getUri')->willReturn($uriMock);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getHeaders')->willReturn($headers);
        $request->method('getHeaderLine')->willReturnMap([
            ['Cookie', http_build_query($cookies, '', '; ')],
            ['Content-Type', $contentType],
        ]);
        $request->method('getCookieParams')->willReturn($cookies);

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($body);
        $request->method('getBody')->willReturn($bodyMock);

        return $request;
    }
}
