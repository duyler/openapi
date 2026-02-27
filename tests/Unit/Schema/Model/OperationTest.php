<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\Servers;

#[CoversClass(Operation::class)]
final class OperationTest extends TestCase
{
    #[Test]
    public function can_create_operation_with_all_fields(): void
    {
        $responses = new Responses(
            responses: ['200' => new Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new Operation(
            responses: $responses,
            summary: 'List users',
            description: 'Get all users',
            operationId: 'listUsers',
            deprecated: false,
        );

        self::assertInstanceOf(Responses::class, $operation->responses);
        self::assertSame('List users', $operation->summary);
        self::assertSame('Get all users', $operation->description);
        self::assertSame('listUsers', $operation->operationId);
        self::assertFalse($operation->deprecated);
    }

    #[Test]
    public function can_create_operation_with_null_fields(): void
    {
        $responses = new Responses(
            responses: ['200' => new Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new Operation(
            responses: $responses,
            summary: null,
            description: null,
            operationId: null,
            deprecated: false,
        );

        self::assertNull($operation->summary);
        self::assertNull($operation->description);
        self::assertNull($operation->operationId);
        self::assertFalse($operation->deprecated);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $responses = new Responses(
            responses: ['200' => new Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new Operation(
            responses: $responses,
            summary: 'List users',
            description: 'Get all users',
            operationId: 'listUsers',
            deprecated: false,
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('operationId', $serialized);
        self::assertSame('List users', $serialized['summary']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $responses = new Responses(
            responses: ['200' => new Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new Operation(
            responses: $responses,
            summary: null,
            description: null,
            operationId: null,
            deprecated: false,
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('summary', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $responses = new Responses(
            responses: ['200' => new Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new Operation(
            tags: ['users'],
            summary: 'List users',
            description: 'Get all users',
            externalDocs: null,
            operationId: 'listUsers',
            parameters: null,
            requestBody: null,
            responses: $responses,
            callbacks: null,
            deprecated: true,
            security: null,
            servers: null,
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('tags', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('operationId', $serialized);
        self::assertArrayHasKey('deprecated', $serialized);
    }

    #[Test]
    public function json_serialize_includes_responses(): void
    {
        $responses = new Responses(
            responses: ['200' => new Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new Operation(
            responses: $responses,
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('responses', $serialized);
    }

    #[Test]
    public function json_serialize_includes_externalDocs(): void
    {
        $operation = new Operation(
            externalDocs: new ExternalDocs(url: 'https://docs.example.com'),
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('externalDocs', $serialized);
    }

    #[Test]
    public function json_serialize_includes_parameters(): void
    {
        $operation = new Operation(
            parameters: new Parameters([]),
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('parameters', $serialized);
    }

    #[Test]
    public function json_serialize_includes_requestBody(): void
    {
        $operation = new Operation(
            requestBody: new RequestBody(
                description: 'Request body',
                content: new Content(
                    mediaTypes: ['application/json' => new MediaType()],
                ),
            ),
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('requestBody', $serialized);
    }

    #[Test]
    public function json_serialize_includes_callbacks(): void
    {
        $operation = new Operation(
            callbacks: new Callbacks(callbacks: []),
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('callbacks', $serialized);
    }

    #[Test]
    public function json_serialize_includes_security(): void
    {
        $operation = new Operation(
            security: new SecurityRequirement([]),
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('security', $serialized);
    }

    #[Test]
    public function json_serialize_includes_servers(): void
    {
        $operation = new Operation(
            servers: new Servers([]),
        );

        $serialized = $operation->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('servers', $serialized);
    }
}
