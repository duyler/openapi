<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Servers;

#[CoversClass(PathItem::class)]
final class PathItemTest extends TestCase
{
    #[Test]
    public function can_create_path_item_with_all_methods(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            get: $operation,
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        self::assertInstanceOf(Operation::class, $pathItem->get);
        self::assertNull($pathItem->post);
    }

    #[Test]
    public function can_create_empty_path_item(): void
    {
        $pathItem = new PathItem(
            get: null,
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        self::assertNull($pathItem->get);
        self::assertNull($pathItem->post);
        self::assertNull($pathItem->put);
    }

    #[Test]
    public function json_serialize_includes_all_methods(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            get: $operation,
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('get', $serialized);
    }

    #[Test]
    public function json_serialize_excludes_null_methods(): void
    {
        $pathItem = new PathItem(
            get: null,
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('get', $serialized);
        self::assertArrayNotHasKey('post', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            ref: '#/components/pathItems/User',
            summary: 'User endpoint',
            description: 'User operations',
            get: $operation,
            put: null,
            post: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
            servers: null,
            parameters: null,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('$ref', $serialized);
        self::assertArrayHasKey('summary', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('get', $serialized);
    }

    #[Test]
    public function json_serialize_includes_servers(): void
    {
        $pathItem = new PathItem(
            servers: new Servers([]),
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('servers', $serialized);
    }

    #[Test]
    public function json_serialize_includes_parameters(): void
    {
        $pathItem = new PathItem(
            parameters: new Parameters([]),
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('parameters', $serialized);
    }

    #[Test]
    public function json_serialize_includes_put(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            put: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('put', $serialized);
    }

    #[Test]
    public function json_serialize_includes_post(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            post: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('post', $serialized);
    }

    #[Test]
    public function json_serialize_includes_delete(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            delete: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('delete', $serialized);
    }

    #[Test]
    public function json_serialize_includes_options(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            options: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('options', $serialized);
    }

    #[Test]
    public function json_serialize_includes_head(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            head: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('head', $serialized);
    }

    #[Test]
    public function json_serialize_includes_patch(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            patch: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('patch', $serialized);
    }

    #[Test]
    public function json_serialize_includes_trace(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            trace: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('trace', $serialized);
    }

    #[Test]
    public function json_serialize_includes_query(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            query: $operation,
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('query', $serialized);
    }

    #[Test]
    public function json_serialize_includes_additional_operations(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            additionalOperations: [
                'COPY' => $operation,
                'MOVE' => $operation,
            ],
        );

        $serialized = $pathItem->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('additionalOperations', $serialized);
        self::assertArrayHasKey('COPY', $serialized['additionalOperations']);
        self::assertArrayHasKey('MOVE', $serialized['additionalOperations']);
    }

    #[Test]
    public function can_create_with_query_and_additional_operations(): void
    {
        $operation = new Operation(
            responses: new Responses(
                responses: ['200' => new Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new PathItem(
            query: $operation,
            additionalOperations: [
                'COPY' => $operation,
                'PURGE' => $operation,
            ],
        );

        self::assertInstanceOf(Operation::class, $pathItem->query);
        self::assertIsArray($pathItem->additionalOperations);
        self::assertCount(2, $pathItem->additionalOperations);
        self::assertArrayHasKey('COPY', $pathItem->additionalOperations);
        self::assertArrayHasKey('PURGE', $pathItem->additionalOperations);
    }
}
