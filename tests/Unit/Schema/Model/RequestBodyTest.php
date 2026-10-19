<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;

use function array_keys;

#[CoversClass(RequestBody::class)]
final class RequestBodyTest extends TestCase
{
    #[Test]
    public function can_create_request_body_with_all_fields(): void
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

        $requestBody = new RequestBody(
            description: 'User to create',
            required: true,
            content: $content,
        );

        self::assertSame('User to create', $requestBody->description);
        self::assertTrue($requestBody->required);
        self::assertInstanceOf(Content::class, $requestBody->content);
    }

    #[Test]
    public function can_create_request_body_with_null_fields(): void
    {
        $requestBody = new RequestBody(
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

        $requestBody = new RequestBody(
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
        $requestBody = new RequestBody(
            description: null,
            required: false,
            content: null,
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('content', $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_returns_only_ref(): void
    {
        $requestBody = new RequestBody(
            ref: '#/components/requestBodies/UserBody',
            description: 'Should be ignored',
            required: true,
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertSame(['$ref' => '#/components/requestBodies/UserBody'], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_includes_summary_and_description_siblings(): void
    {
        $requestBody = new RequestBody(
            ref: '#/components/requestBodies/UserBody',
            refSummary: 'User body summary',
            refDescription: 'User body description',
            description: 'Should be ignored',
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertSame([
            '$ref' => '#/components/requestBodies/UserBody',
            'summary' => 'User body summary',
            'description' => 'User body description',
        ], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_places_ref_as_first_key(): void
    {
        $requestBody = new RequestBody(
            ref: '#/components/requestBodies/UserBody',
            refSummary: 'User body summary',
            refDescription: 'User body description',
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertSame(['$ref', 'summary', 'description'], array_keys($serialized));
    }

    #[Test]
    public function json_serialize_emits_fields_in_wire_order(): void
    {
        $content = new Content(mediaTypes: ['application/json' => new MediaType()]);

        $requestBody = new RequestBody(
            description: 'Description',
            content: $content,
            required: true,
        );

        $serialized = $requestBody->jsonSerialize();

        self::assertSame([
            'description' => 'Description',
            'content' => $content,
            'required' => true,
        ], $serialized);
    }
}
