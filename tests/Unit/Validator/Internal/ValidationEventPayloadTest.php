<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Internal;

use Duyler\OpenApi\Validator\Internal\ValidationEventPayload;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationEventPayload::class)]
final class ValidationEventPayloadTest extends TestCase
{
    #[Test]
    public function defaults_request_response_schemaRef_are_null_when_omitted(): void
    {
        $payload = new ValidationEventPayload(
            request: null,
            response: null,
            path: '/users',
            method: 'GET',
        );

        self::assertNull($payload->request);
        self::assertNull($payload->response);
        self::assertNull($payload->schemaRef);
        self::assertSame('/users', $payload->path);
        self::assertSame('GET', $payload->method);
    }

    #[Test]
    public function carries_request_response_and_schemaRef(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/users');
        $response = $factory->createResponse(200);

        $payload = new ValidationEventPayload(
            request: $request,
            response: $response,
            path: '/users/{id}',
            method: 'POST',
            schemaRef: '#/components/schemas/User',
        );

        self::assertSame($request, $payload->request);
        self::assertSame($response, $payload->response);
        self::assertSame('/users/{id}', $payload->path);
        self::assertSame('POST', $payload->method);
        self::assertSame('#/components/schemas/User', $payload->schemaRef);
    }
}
