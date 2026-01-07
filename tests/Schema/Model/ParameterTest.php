<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Parameter
 */
final class ParameterTest extends TestCase
{
    #[Test]
    public function can_create_parameter_with_all_fields(): void
    {
        $schema = new \Duyler\OpenApi\Schema\Model\Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new \Duyler\OpenApi\Schema\Model\Parameter(
            name: 'id',
            in: 'path',
            description: 'User ID',
            required: true,
            deprecated: false,
            schema: $schema,
        );

        self::assertSame('id', $parameter->name);
        self::assertSame('path', $parameter->in);
        self::assertSame('User ID', $parameter->description);
        self::assertTrue($parameter->required);
        self::assertFalse($parameter->deprecated);
    }

    #[Test]
    public function can_create_parameter_with_null_fields(): void
    {
        $schema = new \Duyler\OpenApi\Schema\Model\Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new \Duyler\OpenApi\Schema\Model\Parameter(
            name: 'page',
            in: 'query',
            description: null,
            required: false,
            deprecated: false,
            schema: $schema,
        );

        self::assertSame('page', $parameter->name);
        self::assertSame('query', $parameter->in);
        self::assertNull($parameter->description);
        self::assertFalse($parameter->required);
        self::assertFalse($parameter->deprecated);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new \Duyler\OpenApi\Schema\Model\Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new \Duyler\OpenApi\Schema\Model\Parameter(
            name: 'id',
            in: 'path',
            description: 'User ID',
            required: true,
            deprecated: false,
            schema: $schema,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayHasKey('in', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertSame('id', $serialized['name']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $schema = new \Duyler\OpenApi\Schema\Model\Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new \Duyler\OpenApi\Schema\Model\Parameter(
            name: 'page',
            in: 'query',
            description: null,
            required: false,
            deprecated: false,
            schema: $schema,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('deprecated', $serialized);
    }
}
