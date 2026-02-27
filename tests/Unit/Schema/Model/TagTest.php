<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

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

    #[Test]
    public function tag_has_summary_field(): void
    {
        $tag = new Tag(
            name: 'Users',
            summary: 'User management',
        );

        self::assertSame('User management', $tag->summary);
    }

    #[Test]
    public function tag_has_parent_field(): void
    {
        $tag = new Tag(
            name: 'Users',
            parent: 'Administration',
        );

        self::assertSame('Administration', $tag->parent);
    }

    #[Test]
    public function tag_has_kind_field(): void
    {
        $tag = new Tag(
            name: 'Users',
            kind: 'nav',
        );

        self::assertSame('nav', $tag->kind);
    }

    #[Test]
    public function tag_has_hierarchy_fields(): void
    {
        $tag = new Tag(
            name: 'Users',
            summary: 'User management',
            parent: 'Administration',
            kind: 'nav',
        );

        self::assertSame('User management', $tag->summary);
        self::assertSame('Administration', $tag->parent);
        self::assertSame('nav', $tag->kind);
    }

    #[Test]
    public function json_serialize_includes_summary(): void
    {
        $tag = new Tag(
            name: 'Users',
            summary: 'User management',
        );

        $serialized = $tag->jsonSerialize();

        self::assertArrayHasKey('summary', $serialized);
        self::assertSame('User management', $serialized['summary']);
    }

    #[Test]
    public function json_serialize_includes_parent(): void
    {
        $tag = new Tag(
            name: 'Users',
            parent: 'Administration',
        );

        $serialized = $tag->jsonSerialize();

        self::assertArrayHasKey('parent', $serialized);
        self::assertSame('Administration', $serialized['parent']);
    }

    #[Test]
    public function json_serialize_includes_kind(): void
    {
        $tag = new Tag(
            name: 'Users',
            kind: 'nav',
        );

        $serialized = $tag->jsonSerialize();

        self::assertArrayHasKey('kind', $serialized);
        self::assertSame('nav', $serialized['kind']);
    }

    #[Test]
    public function json_serialize_excludes_null_hierarchy_fields(): void
    {
        $tag = new Tag(
            name: 'Users',
        );

        $serialized = $tag->jsonSerialize();

        self::assertArrayNotHasKey('summary', $serialized);
        self::assertArrayNotHasKey('parent', $serialized);
        self::assertArrayNotHasKey('kind', $serialized);
    }
}
