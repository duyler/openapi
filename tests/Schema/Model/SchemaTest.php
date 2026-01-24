<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Schema
 */
final class SchemaTest extends TestCase
{
    #[Test]
    public function can_create_schema_with_type(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
        );

        self::assertSame('object', $schema->type);
        self::assertArrayHasKey('id', $schema->properties);
    }

    #[Test]
    public function can_create_schema_with_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
            required: ['id'],
            description: 'User schema',
        );

        self::assertSame('object', $schema->type);
        self::assertArrayHasKey('id', $schema->properties);
        self::assertContains('id', $schema->required);
        self::assertSame('User schema', $schema->description);
    }

    #[Test]
    public function can_create_schema_with_null_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
            required: null,
            description: null,
        );

        self::assertSame('string', $schema->type);
        self::assertNull($schema->properties);
        self::assertNull($schema->required);
        self::assertNull($schema->description);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
            required: ['id'],
            description: 'User schema',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayHasKey('properties', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('object', $serialized['type']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
            required: null,
            description: null,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayNotHasKey('properties', $serialized);
        self::assertArrayNotHasKey('required', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
    }
}
