<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Tag;
use Duyler\OpenApi\Schema\Model\Tags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Tags
 */
final class TagsTest extends TestCase
{
    #[Test]
    public function can_create_tags(): void
    {
        $tag = new Tag(
            name: 'users',
            description: 'User operations',
        );

        $tags = new Tags(
            tags: [$tag],
        );

        self::assertCount(1, $tags->tags);
        self::assertSame('users', $tags->tags[0]->name);
    }

    #[Test]
    public function can_create_empty_tags(): void
    {
        $tags = new Tags(
            tags: [],
        );

        self::assertCount(0, $tags->tags);
    }

    #[Test]
    public function json_serialize_includes_tags(): void
    {
        $tag = new Tag(
            name: 'users',
            description: 'User operations',
        );

        $tags = new Tags(
            tags: [$tag],
        );

        $serialized = $tags->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('tags', $serialized);
        self::assertIsArray($serialized['tags']);
        self::assertCount(1, $serialized['tags']);
        self::assertIsArray($serialized['tags'][0]);
        self::assertArrayHasKey('name', $serialized['tags'][0]);
    }
}
