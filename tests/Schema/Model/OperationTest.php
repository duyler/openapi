<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Operation
 */
final class OperationTest extends TestCase
{
    #[Test]
    public function can_create_operation_with_all_fields(): void
    {
        $responses = new \Duyler\OpenApi\Schema\Model\Responses(
            responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new \Duyler\OpenApi\Schema\Model\Operation(
            responses: $responses,
            summary: 'List users',
            description: 'Get all users',
            operationId: 'listUsers',
            deprecated: false,
        );

        self::assertInstanceOf(\Duyler\OpenApi\Schema\Model\Responses::class, $operation->responses);
        self::assertSame('List users', $operation->summary);
        self::assertSame('Get all users', $operation->description);
        self::assertSame('listUsers', $operation->operationId);
        self::assertFalse($operation->deprecated);
    }

    #[Test]
    public function can_create_operation_with_null_fields(): void
    {
        $responses = new \Duyler\OpenApi\Schema\Model\Responses(
            responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new \Duyler\OpenApi\Schema\Model\Operation(
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
        $responses = new \Duyler\OpenApi\Schema\Model\Responses(
            responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new \Duyler\OpenApi\Schema\Model\Operation(
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
        $responses = new \Duyler\OpenApi\Schema\Model\Responses(
            responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                description: 'Success',
                headers: null,
                content: null,
            )],
        );

        $operation = new \Duyler\OpenApi\Schema\Model\Operation(
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
}
