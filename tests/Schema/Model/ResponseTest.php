<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    #[Test]
    public function can_create_response_with_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $content = new Content(
            mediaTypes: ['application/json' => new MediaType(
                schema: $schema,
                example: null,
            )],
        );

        $response = new Response(
            description: 'Success',
            headers: null,
            content: $content,
        );

        self::assertSame('Success', $response->description);
        self::assertNull($response->headers);
        self::assertInstanceOf(Content::class, $response->content);
    }

    #[Test]
    public function can_create_response_with_null_fields(): void
    {
        $response = new Response(
            description: 'Success',
            headers: null,
            content: null,
        );

        self::assertSame('Success', $response->description);
        self::assertNull($response->headers);
        self::assertNull($response->content);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $response = new Response(
            description: 'Success',
            headers: null,
            content: null,
        );

        $serialized = $response->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('Success', $serialized['description']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $response = new Response(
            description: 'Success',
            headers: null,
            content: null,
        );

        $serialized = $response->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayNotHasKey('headers', $serialized);
        self::assertArrayNotHasKey('content', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $content = new Content(
            mediaTypes: ['application/json' => new MediaType(
                schema: $schema,
                example: null,
            )],
        );

        $response = new Response(
            description: 'Success',
            headers: null,
            content: $content,
            links: null,
        );

        $serialized = $response->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('content', $serialized);
    }

    #[Test]
    public function json_serialize_includes_headers(): void
    {
        $response = new Response(
            headers: new Headers([]),
        );

        $serialized = $response->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('headers', $serialized);
    }

    #[Test]
    public function json_serialize_includes_links(): void
    {
        $response = new Response(
            links: new Links([]),
        );

        $serialized = $response->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('links', $serialized);
    }
}
