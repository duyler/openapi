<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\RequestBody
 */
final class RequestBodyTest extends TestCase
{
    #[Test]
    public function can_create_request_body_with_all_fields(): void
    {
        $schema = new \Duyler\OpenApi\Schema\Model\Schema(
            type: 'object',
            properties: null,
        );

        $content = new \Duyler\OpenApi\Schema\Model\Content(
            mediaTypes: ['application/json' => new \Duyler\OpenApi\Schema\Model\MediaType(
                schema: $schema,
                example: null,
            )],
        );

        $requestBody = new \Duyler\OpenApi\Schema\Model\RequestBody(
            description: 'User to create',
            required: true,
            content: $content,
        );

        self::assertSame('User to create', $requestBody->description);
        self::assertTrue($requestBody->required);
        self::assertInstanceOf(\Duyler\OpenApi\Schema\Model\Content::class, $requestBody->content);
    }

    #[Test]
    public function can_create_request_body_with_null_fields(): void
    {
        $requestBody = new \Duyler\OpenApi\Schema\Model\RequestBody(
            description: null,
            required: false,
            content: null,
        );

        self::assertNull($requestBody->description);
        self::assertFalse($requestBody->required);
        self::assertNull($requestBody->content);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new \Duyler\OpenApi\Schema\Model\Schema(
            type: 'object',
            properties: null,
        );

        $content = new \Duyler\OpenApi\Schema\Model\Content(
            mediaTypes: ['application/json' => new \Duyler\OpenApi\Schema\Model\MediaType(
                schema: $schema,
                example: null,
            )],
        );

        $requestBody = new \Duyler\OpenApi\Schema\Model\RequestBody(
            description: 'User to create',
            required: true,
            content: $content,
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertSame('User to create', $serialized['description']);
        self::assertTrue($serialized['required']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $requestBody = new \Duyler\OpenApi\Schema\Model\RequestBody(
            description: null,
            required: false,
            content: null,
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('content', $serialized);
    }
}
