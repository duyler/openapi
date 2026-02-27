<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\MediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

#[CoversClass(Header::class)]
final class HeaderTest extends TestCase
{
    #[Test]
    public function can_create_header_with_all_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: 'Custom header',
            required: false,
            deprecated: false,
            schema: $schema,
        );

        self::assertSame('Custom header', $header->description);
        self::assertFalse($header->required);
        self::assertFalse($header->deprecated);
    }

    #[Test]
    public function can_create_header_with_null_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: null,
            required: false,
            deprecated: false,
            schema: $schema,
        );

        self::assertNull($header->description);
        self::assertFalse($header->required);
        self::assertFalse($header->deprecated);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: 'Custom header',
            required: true,
            deprecated: false,
            schema: $schema,
        );

        $serialized = $header->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertSame('Custom header', $serialized['description']);
        self::assertTrue($serialized['required']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: null,
            required: false,
            deprecated: false,
            schema: $schema,
        );

        $serialized = $header->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('deprecated', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: 'Custom header',
            required: true,
            deprecated: true,
            allowEmptyValue: true,
            schema: $schema,
            example: 'test',
            examples: ['example1' => 'value1'],
            content: null,
        );

        $serialized = $header->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertArrayHasKey('deprecated', $serialized);
        self::assertArrayHasKey('allowEmptyValue', $serialized);
        self::assertArrayHasKey('schema', $serialized);
        self::assertArrayHasKey('example', $serialized);
        self::assertArrayHasKey('examples', $serialized);
    }

    #[Test]
    public function json_serialize_includes_content(): void
    {
        $header = new Header(
            content: new Content(
                mediaTypes: ['application/json' => new MediaType()],
            ),
        );

        $serialized = $header->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('content', $serialized);
    }
}
