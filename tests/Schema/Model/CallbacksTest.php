<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\PathItem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Callbacks
 */
final class CallbacksTest extends TestCase
{
    #[Test]
    public function can_create_callbacks(): void
    {
        $pathItem = new PathItem(
            get: new Operation(
                responses: new Responses(
                    responses: ['200' => new Response(
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

        $callbacks = new Callbacks(
            callbacks: ['myCallback' => ['{$request.query#/url}' => $pathItem]],
        );

        self::assertArrayHasKey('myCallback', $callbacks->callbacks);
        self::assertIsArray($callbacks->callbacks['myCallback']);
    }

    #[Test]
    public function can_create_empty_callbacks(): void
    {
        $callbacks = new Callbacks(
            callbacks: [],
        );

        self::assertCount(0, $callbacks->callbacks);
    }

    #[Test]
    public function json_serialize_includes_callbacks(): void
    {
        $pathItem = new PathItem(
            get: null,
            post: new Operation(
                responses: new Responses(
                    responses: ['200' => new Response(
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

        $callbacks = new Callbacks(
            callbacks: ['myCallback' => ['{$request.query#/url}' => $pathItem]],
        );

        $serialized = $callbacks->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('myCallback', $serialized);
        self::assertIsArray($serialized['myCallback']);
    }
}
