<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/** @internal */
final class RealOpenApiSpecTest extends TestCase
{
    private object $petstoreValidator;
    private object $ecommerceValidator;

    protected function setUp(): void
    {
        $this->petstoreValidator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->build();

        $this->ecommerceValidator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/real-world-api.yaml')
            ->build();
    }

    #[Test]
    public function validate_petstore_spec_loaded(): void
    {
        $document = $this->petstoreValidator->document;

        self::assertSame('3.1.0', $document->openapi);
        self::assertSame('Petstore API', $document->info->title);
        self::assertSame('1.0.0', $document->info->version);
    }

    #[Test]
    public function validate_petstore_list_pets_request(): void
    {
        $request = $this->createPsr7Request(method: 'GET', uri: '/pets');

        $this->petstoreValidator->validateRequest($request, '/pets', 'GET');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_petstore_create_pet_request(): void
    {
        $request = $this->createPsr7Request(
            method: 'POST',
            uri: '/pets',
            headers: ['Content-Type' => 'application/json'],
            body: '{"name":"Fluffy","tag":"cat"}',
        );

        $this->petstoreValidator->validateRequest($request, '/pets', 'POST');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_petstore_get_pet_by_id(): void
    {
        $request = $this->createPsr7Request(method: 'GET', uri: '/pets/123');

        $this->petstoreValidator->validateRequest($request, '/pets/{petId}', 'GET');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_petstore_response_schema(): void
    {
        $response = $this->createPsr7Response(
            statusCode: 200,
            headers: ['Content-Type' => 'application/json'],
            body: '[{"id":1,"name":"Fluffy","tag":"cat"},{"id":2,"name":"Buddy","tag":"dog"}]',
        );

        $this->petstoreValidator->validateResponse($response, '/pets', 'GET');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_petstore_invalid_request_throws(): void
    {
        $request = $this->createPsr7Request(
            method: 'POST',
            uri: '/pets',
            headers: ['Content-Type' => 'application/json'],
            body: '{"tag":"cat"}',
        );

        $this->expectException(ValidationException::class);

        $this->petstoreValidator->validateRequest($request, '/pets', 'POST');
    }

    #[Test]
    public function validate_ecommerce_create_order(): void
    {
        $request = $this->createPsr7Request(
            method: 'POST',
            uri: '/orders',
            headers: ['Content-Type' => 'application/json'],
            body: '{"customer_id":"123e4567-e89b-12d3-a456-426614174000","items":[{"product_id":"prod_123","quantity":2}]}',
        );

        $this->ecommerceValidator->validateRequest($request, '/orders', 'POST');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_ecommerce_get_order(): void
    {
        $request = $this->createPsr7Request(
            method: 'GET',
            uri: '/orders/123e4567-e89b-12d3-a456-426614174000',
        );

        $this->ecommerceValidator->validateRequest($request, '/orders/{orderId}', 'GET');

        $this->expectNotToPerformAssertions();
    }

    private function createPsr7Request(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
    ): ServerRequestInterface {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);

        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn($uri);
        $uriMock->method('getQuery')->willReturn('');

        $request->method('getUri')->willReturn($uriMock);
        $request->method('getHeaders')->willReturn($headers);
        $request->method('getHeaderLine')->willReturnCallback(
            fn($headerName) => $headers[$headerName] ?? '',
        );

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($body);
        $request->method('getBody')->willReturn($bodyMock);

        return $request;
    }

    private function createPsr7Response(
        int $statusCode,
        array $headers = [],
        string $body = '',
    ): ResponseInterface {
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getHeaderLine')->willReturn('application/json');

        $bodyMock = $this->createMock(StreamInterface::class);
        $bodyMock->method('__toString')->willReturn($body);
        $response->method('getBody')->willReturn($bodyMock);

        return $response;
    }
}
