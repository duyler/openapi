<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;

#[CoversClass(Paths::class)]
final class PathsTest extends TestCase
{
    #[Test]
    public function can_create_paths(): void
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

        $paths = new Paths(
            paths: ['/users' => $pathItem],
        );

        self::assertArrayHasKey('/users', $paths->paths);
        self::assertInstanceOf(PathItem::class, $paths->paths['/users']);
    }

    #[Test]
    public function can_create_empty_paths(): void
    {
        $paths = new Paths(
            paths: [],
        );

        self::assertCount(0, $paths->paths);
    }

    #[Test]
    public function json_serialize_includes_paths(): void
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

        $paths = new Paths(
            paths: ['/users' => $pathItem],
        );

        $serialized = $paths->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('/users', $serialized);
        self::assertIsArray($serialized['/users']);
        self::assertArrayHasKey('get', $serialized['/users']);
    }
}
