<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\PathItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Callbacks
 */
final class CallbacksTest extends TestCase
{
    #[Test]
    public function can_create_callbacks(): void
    {
        $pathItem = new PathItem(
            get: new \Duyler\OpenApi\Schema\Model\Operation(
                responses: new \Duyler\OpenApi\Schema\Model\Responses(
                    responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                        description: 'Success',
                        headers: null,
                        content: null,
                    )],
                ),
            ),
            post: null,
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        $callbacks = new \Duyler\OpenApi\Schema\Model\Callbacks(
            callbacks: ['myCallback' => ['{$request.query#/url}' => $pathItem]],
        );

        self::assertArrayHasKey('myCallback', $callbacks->callbacks);
        self::assertIsArray($callbacks->callbacks['myCallback']);
    }

    #[Test]
    public function can_create_empty_callbacks(): void
    {
        $callbacks = new \Duyler\OpenApi\Schema\Model\Callbacks(
            callbacks: [],
        );

        self::assertCount(0, $callbacks->callbacks);
    }

    #[Test]
    public function json_serialize_includes_callbacks(): void
    {
        $pathItem = new PathItem(
            get: null,
            post: new \Duyler\OpenApi\Schema\Model\Operation(
                responses: new \Duyler\OpenApi\Schema\Model\Responses(
                    responses: ['200' => new \Duyler\OpenApi\Schema\Model\Response(
                        description: 'Success',
                        headers: null,
                        content: null,
                    )],
                ),
            ),
            put: null,
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        $callbacks = new \Duyler\OpenApi\Schema\Model\Callbacks(
            callbacks: ['myCallback' => ['{$request.query#/url}' => $pathItem]],
        );

        $serialized = $callbacks->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('myCallback', $serialized);
        self::assertIsArray($serialized['myCallback']);
    }
}
