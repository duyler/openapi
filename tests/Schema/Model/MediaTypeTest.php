<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\MediaType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @covers \Duyler\OpenApi\Schema\Model\MediaType
 */
final class MediaTypeTest extends TestCase
{
    #[Test]
    public function can_create_media_type_with_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $mediaType = new MediaType(
            schema: $schema,
            example: null,
        );

        self::assertSame($schema, $mediaType->schema);
        self::assertNull($mediaType->example);
    }

    #[Test]
    public function can_create_media_type_with_null_fields(): void
    {
        $mediaType = new MediaType(
            schema: null,
            example: null,
        );

        self::assertNull($mediaType->schema);
        self::assertNull($mediaType->example);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $mediaType = new MediaType(
            schema: $schema,
            example: null,
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('schema', $serialized);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $mediaType = new MediaType(
            schema: null,
            example: null,
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('schema', $serialized);
        self::assertArrayNotHasKey('example', $serialized);
    }
}
