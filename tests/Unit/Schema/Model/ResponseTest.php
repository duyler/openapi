<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

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

    #[Test]
    public function response_has_summary_field(): void
    {
        $response = new Response(
            summary: 'Successful response',
            description: 'Success',
        );

        self::assertSame('Successful response', $response->summary);
    }

    #[Test]
    public function json_serialize_includes_summary(): void
    {
        $response = new Response(
            summary: 'Successful response',
            description: 'Success',
        );

        $serialized = $response->jsonSerialize();

        self::assertArrayHasKey('summary', $serialized);
        self::assertSame('Successful response', $serialized['summary']);
    }

    #[Test]
    public function json_serialize_excludes_null_summary(): void
    {
        $response = new Response(
            description: 'Success',
        );

        $serialized = $response->jsonSerialize();

        self::assertArrayNotHasKey('summary', $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_returns_only_ref(): void
    {
        $response = new Response(
            ref: '#/components/responses/NotFound',
            description: 'Should be ignored',
            headers: new Headers([]),
            content: new Content([]),
            links: new Links([]),
        );

        $serialized = $response->jsonSerialize();

        self::assertSame(['$ref' => '#/components/responses/NotFound'], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_includes_summary_sibling(): void
    {
        $response = new Response(
            ref: '#/components/responses/NotFound',
            refSummary: 'Not Found summary',
        );

        $serialized = $response->jsonSerialize();

        self::assertSame([
            '$ref' => '#/components/responses/NotFound',
            'summary' => 'Not Found summary',
        ], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_includes_description_sibling(): void
    {
        $response = new Response(
            ref: '#/components/responses/NotFound',
            refDescription: 'Not Found description',
        );

        $serialized = $response->jsonSerialize();

        self::assertSame([
            '$ref' => '#/components/responses/NotFound',
            'description' => 'Not Found description',
        ], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_includes_both_siblings(): void
    {
        $response = new Response(
            ref: '#/components/responses/NotFound',
            refSummary: 'Summary override',
            refDescription: 'Description override',
        );

        $serialized = $response->jsonSerialize();

        self::assertSame([
            '$ref' => '#/components/responses/NotFound',
            'summary' => 'Summary override',
            'description' => 'Description override',
        ], $serialized);
    }
}
