<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Links::class)]
final class LinksTest extends TestCase
{
    #[Test]
    public function can_create_links(): void
    {
        $link = new Link(
            operationRef: 'operationId',
            parameters: ['id' => '$request.path.id'],
        );

        $links = new Links(
            links: ['UserLink' => $link],
        );

        self::assertArrayHasKey('UserLink', $links->links);
        self::assertInstanceOf(Link::class, $links->links['UserLink']);
    }

    #[Test]
    public function can_create_empty_links(): void
    {
        $links = new Links(
            links: [],
        );

        self::assertCount(0, $links->links);
    }

    #[Test]
    public function json_serialize_includes_links(): void
    {
        $link = new Link(
            operationRef: 'operationId',
            parameters: null,
        );

        $links = new Links(
            links: ['UserLink' => $link],
        );

        $serialized = $links->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('UserLink', $serialized);
        self::assertIsArray($serialized['UserLink']);
        self::assertArrayHasKey('operationRef', $serialized['UserLink']);
    }
}
