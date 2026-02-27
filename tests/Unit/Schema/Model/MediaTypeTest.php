<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\MediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

#[CoversClass(MediaType::class)]
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

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $mediaType = new MediaType(
            schema: $schema,
            examples: ['example1' => ['test' => 'value']],
            example: null,
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('schema', $serialized);
        self::assertArrayHasKey('examples', $serialized);
    }

    #[Test]
    public function json_serialize_includes_example(): void
    {
        $example = new Example(
            summary: 'Test example',
            value: ['test' => 'data'],
        );

        $mediaType = new MediaType(
            example: $example,
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('example', $serialized);
    }

    #[Test]
    public function supports_item_schema_for_streaming(): void
    {
        $itemSchema = new Schema(
            type: 'object',
            properties: null,
        );

        $mediaType = new MediaType(
            itemSchema: $itemSchema,
        );

        self::assertSame($itemSchema, $mediaType->itemSchema);
        self::assertNull($mediaType->schema);
    }

    #[Test]
    public function supports_item_encoding_for_streaming(): void
    {
        $itemEncoding = new Encoding(
            contentType: 'application/json',
        );

        $mediaType = new MediaType(
            itemEncoding: $itemEncoding,
        );

        self::assertSame($itemEncoding, $mediaType->itemEncoding);
    }

    #[Test]
    public function supports_prefix_encoding(): void
    {
        $prefixEncoding1 = new Encoding(contentType: 'application/json');
        $prefixEncoding2 = new Encoding(contentType: 'text/plain');

        $mediaType = new MediaType(
            prefixEncoding: [$prefixEncoding1, $prefixEncoding2],
        );

        self::assertNotNull($mediaType->prefixEncoding);
        self::assertCount(2, $mediaType->prefixEncoding);
    }

    #[Test]
    public function supports_encoding_map(): void
    {
        $encoding1 = new Encoding(contentType: 'application/json');

        $mediaType = new MediaType(
            encoding: ['field1' => $encoding1],
        );

        self::assertNotNull($mediaType->encoding);
        self::assertArrayHasKey('field1', $mediaType->encoding);
    }

    #[Test]
    public function json_serialize_includes_item_schema(): void
    {
        $itemSchema = new Schema(type: 'object', properties: null);
        $mediaType = new MediaType(itemSchema: $itemSchema);

        $serialized = $mediaType->jsonSerialize();

        self::assertArrayHasKey('itemSchema', $serialized);
    }

    #[Test]
    public function json_serialize_includes_item_encoding(): void
    {
        $mediaType = new MediaType(
            itemEncoding: new Encoding(contentType: 'application/json'),
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertArrayHasKey('itemEncoding', $serialized);
    }

    #[Test]
    public function json_serialize_includes_prefix_encoding(): void
    {
        $mediaType = new MediaType(
            prefixEncoding: [new Encoding(contentType: 'application/json')],
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertArrayHasKey('prefixEncoding', $serialized);
    }

    #[Test]
    public function json_serialize_includes_encoding(): void
    {
        $mediaType = new MediaType(
            encoding: ['field1' => new Encoding(contentType: 'application/json')],
        );

        $serialized = $mediaType->jsonSerialize();

        self::assertArrayHasKey('encoding', $serialized);
    }

    #[Test]
    public function can_have_both_schema_and_item_schema(): void
    {
        $schema = new Schema(type: 'array', properties: null);
        $itemSchema = new Schema(type: 'object', properties: null);

        $mediaType = new MediaType(
            schema: $schema,
            itemSchema: $itemSchema,
        );

        self::assertSame($schema, $mediaType->schema);
        self::assertSame($itemSchema, $mediaType->itemSchema);
    }
}
