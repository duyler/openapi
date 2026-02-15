<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\Tag;

final class TagTest extends TestCase
{
    #[Test]
    public function json_serialize_includes_description(): void
    {
        $tag = new Tag(
            name: 'users',
            description: 'Operations about users',
        );

        $serialized = $tag->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('users', $serialized['name']);
        self::assertSame('Operations about users', $serialized['description']);
    }

    #[Test]
    public function json_serialize_includes_externalDocs(): void
    {
        $tag = new Tag(
            name: 'users',
            externalDocs: new ExternalDocs(
                url: 'https://docs.example.com/users',
            ),
        );

        $serialized = $tag->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayHasKey('externalDocs', $serialized);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $tag = new Tag(
            name: 'users',
        );

        $serialized = $tag->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('externalDocs', $serialized);
    }
}
