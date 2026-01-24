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
        self::assertArrayHasKey('{$request.query#/url}', $serialized['myCallback']);
        self::assertIsArray($serialized['myCallback']['{$request.query#/url}']);
    }

    #[Test]
    public function json_serialize_includes_all_expressions(): void
    {
        $pathItem1 = new PathItem(
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

        $pathItem2 = new PathItem(
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
            callbacks: [
                'myCallback' => [
                    '{$request.query#/url}' => $pathItem1,
                    '{$request.body#/user}' => $pathItem2,
                ],
            ],
        );

        $serialized = $callbacks->jsonSerialize();

        self::assertArrayHasKey('myCallback', $serialized);
        self::assertArrayHasKey('{$request.query#/url}', $serialized['myCallback']);
        self::assertArrayHasKey('{$request.body#/user}', $serialized['myCallback']);
        self::assertArrayHasKey('get', $serialized['myCallback']['{$request.query#/url}']);
        self::assertArrayHasKey('post', $serialized['myCallback']['{$request.body#/user}']);
    }

    #[Test]
    public function json_serialize_preserves_all_data_structure(): void
    {
        $pathItem1 = new PathItem(
            get: new Operation(
                responses: new Responses(
                    responses: ['200' => new Response(
                        description: 'First',
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

        $pathItem2 = new PathItem(
            get: null,
            post: new Operation(
                responses: new Responses(
                    responses: ['200' => new Response(
                        description: 'Second',
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

        $pathItem3 = new PathItem(
            get: null,
            post: null,
            put: new Operation(
                responses: new Responses(
                    responses: ['200' => new Response(
                        description: 'Third',
                        headers: null,
                        content: null,
                    )],
                ),
            ),
            delete: null,
            options: null,
            head: null,
            patch: null,
            trace: null,
        );

        $callbacks = new Callbacks(
            callbacks: [
                'callback1' => [
                    '{$request.query#/url}' => $pathItem1,
                    '{$request.body#/user}' => $pathItem2,
                ],
                'callback2' => [
                    '{$request.header#/auth}' => $pathItem3,
                ],
            ],
        );

        $serialized = $callbacks->jsonSerialize();

        self::assertCount(2, $serialized);
        self::assertArrayHasKey('callback1', $serialized);
        self::assertArrayHasKey('callback2', $serialized);
        self::assertCount(2, $serialized['callback1']);
        self::assertCount(1, $serialized['callback2']);
    }
}
