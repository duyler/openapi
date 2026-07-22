<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Webhook;

use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Duyler\OpenApi\Validator\Webhook\Exception\UnknownWebhookException;
use Duyler\OpenApi\Validator\Webhook\WebhookValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(WebhookValidator::class)]
final class WebhookValidatorTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function validate_throws_when_webhook_unknown(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $validator = new WebhookValidator($requestValidator);

        $document = $this->buildDocumentWithWebhook(
            'payment.updated',
            new PathItem(post: new SchemaOperation(operationId: 'paymentUpdated')),
        );
        $request = $this->buildRequest('POST', '/hook');

        $this->expectException(UnknownWebhookException::class);
        $this->expectExceptionMessage('Unknown webhook: missing.webhook');

        $validator->validate($request, 'missing.webhook', $document);
    }

    #[Test]
    public function validate_throws_when_document_has_no_webhooks_field_at_all(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $validator = new WebhookValidator($requestValidator);

        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'No webhooks', version: '1.0.0'),
        );
        $request = $this->buildRequest('POST', '/hook');

        $this->expectException(UnknownWebhookException::class);
        $this->expectExceptionMessage('Unknown webhook: payment.updated');

        $validator->validate($request, 'payment.updated', $document);
    }

    #[Test]
    public function validate_throws_when_method_not_defined_in_path_item(): void
    {
        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $validator = new WebhookValidator($requestValidator);

        $document = $this->buildDocumentWithWebhook(
            'payment.updated',
            new PathItem(post: new SchemaOperation(operationId: 'paymentUpdated')),
        );
        $request = $this->buildRequest('GET', '/hook');

        $this->expectException(UnknownWebhookException::class);
        $this->expectExceptionMessage('payment.updated (method: get)');

        $validator->validate($request, 'payment.updated', $document);
    }

    #[Test]
    public function validate_delegates_to_request_validator_with_extracted_path(): void
    {
        $operation = new SchemaOperation(operationId: 'paymentUpdated');
        $document = $this->buildDocumentWithWebhook(
            'payment.updated',
            new PathItem(post: $operation),
        );
        $request = $this->buildRequest('POST', '/webhook/payment/updated');

        $requestValidator = $this->createMock(RequestValidatorInterface::class);
        $requestValidator->expects($this->once())
            ->method('validate')
            ->with($request, $operation, '/webhook/payment/updated');

        $validator = new WebhookValidator($requestValidator);

        $validator->validate($request, 'payment.updated', $document);
    }

    #[Test]
    public function validate_returns_operation_from_path_item(): void
    {
        $operation = new SchemaOperation(operationId: 'paymentUpdated');
        $document = $this->buildDocumentWithWebhook(
            'payment.updated',
            new PathItem(post: $operation),
        );
        $request = $this->buildRequest('POST', '/hook');

        $requestValidator = $this->createStub(RequestValidatorInterface::class);
        $validator = new WebhookValidator($requestValidator);

        $result = $validator->validate($request, 'payment.updated', $document);

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function validate_normalises_request_method_to_lower_case_before_lookup(): void
    {
        $operation = new SchemaOperation(operationId: 'paymentUpdated');
        $document = $this->buildDocumentWithWebhook(
            'payment.updated',
            new PathItem(post: $operation),
        );
        $request = $this->buildRequest('POST', '/hook');

        $requestValidator = $this->createMock(RequestValidatorInterface::class);
        $requestValidator->expects($this->once())
            ->method('validate')
            ->with($request, $operation, '/hook');

        $validator = new WebhookValidator($requestValidator);

        $result = $validator->validate($request, 'payment.updated', $document);

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function validate_passes_request_uri_path_to_request_validator(): void
    {
        $operation = new SchemaOperation(operationId: 'paymentUpdated');
        $document = $this->buildDocumentWithWebhook(
            'payment.updated',
            new PathItem(post: $operation),
        );
        $expectedPath = '/custom/webhook/path';

        $capturedPath = null;
        $requestValidator = $this->createMock(RequestValidatorInterface::class);
        $requestValidator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(
                function (
                    ServerRequestInterface $request,
                    SchemaOperation $op,
                    string $path,
                ) use ($operation, &$capturedPath): SchemaOperation {
                    $capturedPath = $path;

                    $this->assertSame($operation, $op);

                    return $op;
                },
            );

        $validator = new WebhookValidator($requestValidator);
        $request = $this->buildRequest('POST', $expectedPath);

        $validator->validate($request, 'payment.updated', $document);

        $this->assertSame($expectedPath, $capturedPath);
    }

    private function buildDocumentWithWebhook(string $name, PathItem $pathItem): OpenApiDocument
    {
        return new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Webhook test', version: '1.0.0'),
            webhooks: new Webhooks([$name => $pathItem]),
        );
    }

    private function buildRequest(string $method, string $path): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest($method, $path);
    }
}
