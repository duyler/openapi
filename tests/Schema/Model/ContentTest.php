<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Content
 */
final class ContentTest extends TestCase
{
    #[Test]
    public function can_create_content(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $mediaType = new MediaType(
            schema: $schema,
            example: null,
        );

        $content = new Content(
            mediaTypes: ['application/json' => $mediaType],
        );

        self::assertArrayHasKey('application/json', $content->mediaTypes);
        self::assertInstanceOf(MediaType::class, $content->mediaTypes['application/json']);
    }

    #[Test]
    public function can_create_empty_content(): void
    {
        $content = new Content(
            mediaTypes: [],
        );

        self::assertCount(0, $content->mediaTypes);
    }

    #[Test]
    public function json_serialize_includes_content(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $mediaType = new MediaType(
            schema: $schema,
            example: null,
        );

        $content = new Content(
            mediaTypes: ['application/json' => $mediaType],
        );

        $serialized = $content->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('application/json', $serialized);
        self::assertIsArray($serialized['application/json']);
    }
}
