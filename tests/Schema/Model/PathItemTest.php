<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\PathItem
 */
final class PathItemTest extends TestCase
{
    #[Test]
    public function can_create_path_item_with_all_methods(): void
    {
        $operation = new \Duyler\OpenApi\Schema\Model\Operation(
            responses: new \Duyler\OpenApi\Schema\Model\Responses(
                responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new \Duyler\OpenApi\Schema\Model\PathItem(
            get: $operation,
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        self::assertInstanceOf(\Duyler\OpenApi\Schema\Model\Operation::class, $pathItem->get);
        self::assertNull($pathItem->post);
    }

    #[Test]
    public function can_create_empty_path_item(): void
    {
        $pathItem = new \Duyler\OpenApi\Schema\Model\PathItem(
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
        $operation = new \Duyler\OpenApi\Schema\Model\Operation(
            responses: new \Duyler\OpenApi\Schema\Model\Responses(
                responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                    description: 'Success',
                    headers: null,
                    content: null,
                )],
            ),
        );

        $pathItem = new \Duyler\OpenApi\Schema\Model\PathItem(
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
        $pathItem = new \Duyler\OpenApi\Schema\Model\PathItem(
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
}
