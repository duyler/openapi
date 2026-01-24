<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Webhooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;

#[CoversClass(Webhooks::class)]
final class WebhooksTest extends TestCase
{
    #[Test]
    public function can_create_webhooks(): void
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

        $webhooks = new Webhooks(
            webhooks: ['newPet' => $pathItem],
        );

        self::assertArrayHasKey('newPet', $webhooks->webhooks);
        self::assertInstanceOf(PathItem::class, $webhooks->webhooks['newPet']);
    }

    #[Test]
    public function can_create_empty_webhooks(): void
    {
        $webhooks = new Webhooks(
            webhooks: [],
        );

        self::assertCount(0, $webhooks->webhooks);
    }

    #[Test]
    public function json_serialize_includes_webhooks(): void
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

        $webhooks = new Webhooks(
            webhooks: ['newPet' => $pathItem],
        );

        $serialized = $webhooks->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('newPet', $serialized);
        self::assertIsArray($serialized['newPet']);
        self::assertArrayHasKey('post', $serialized['newPet']);
    }
}
