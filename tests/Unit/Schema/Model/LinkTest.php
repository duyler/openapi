<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Server;

use function array_key_first;
use function array_keys;

#[CoversClass(Link::class)]
final class LinkTest extends TestCase
{
    #[Test]
    public function can_create_link_with_all_fields(): void
    {
        $link = new Link(
            operationRef: 'operationId',
            operationId: null,
            parameters: ['id' => '$request.path.id'],
            requestBody: null,
            description: 'Link to user',
        );

        self::assertSame('operationId', $link->operationRef);
        self::assertSame(['id' => '$request.path.id'], $link->parameters);
        self::assertSame('Link to user', $link->description);
    }

    #[Test]
    public function can_create_link_with_null_fields(): void
    {
        $link = new Link(
            operationRef: null,
            operationId: 'getPetById',
            parameters: null,
            requestBody: null,
            description: null,
        );

        self::assertNull($link->operationRef);
        self::assertSame('getPetById', $link->operationId);
        self::assertNull($link->parameters);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $link = new Link(
            operationRef: 'operationId',
            operationId: null,
            parameters: ['id' => '$request.path.id'],
            requestBody: null,
            description: 'Link to user',
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('operationRef', $serialized);
        self::assertArrayHasKey('parameters', $serialized);
        self::assertSame('operationId', $serialized['operationRef']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $link = new Link(
            operationRef: null,
            operationId: null,
            parameters: null,
            requestBody: null,
            description: null,
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('operationRef', $serialized);
        self::assertArrayNotHasKey('parameters', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $link = new Link(
            operationRef: 'operationId',
            ref: null,
            description: 'Link to user',
            operationId: null,
            parameters: ['id' => '$request.path.id'],
            requestBody: null,
            server: null,
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('operationRef', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('parameters', $serialized);
    }

    #[Test]
    public function json_serialize_includes_ref(): void
    {
        $link = new Link(
            ref: '#/components/links/UserLink',
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('$ref', $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_keeps_siblings(): void
    {
        $link = new Link(
            operationRef: '#/operations/getUser',
            ref: '#/components/links/UserLink',
            description: 'cross-ref link',
            parameters: ['id' => '$response.body#/id'],
        );

        $serialized = $link->jsonSerialize();

        self::assertSame(
            [
                '$ref' => '#/components/links/UserLink',
                'operationRef' => '#/operations/getUser',
                'description' => 'cross-ref link',
                'parameters' => ['id' => '$response.body#/id'],
            ],
            $serialized,
        );
    }

    #[Test]
    public function json_serialize_ref_object_places_ref_as_first_key(): void
    {
        $link = new Link(
            operationRef: '#/operations/getUser',
            ref: '#/components/links/UserLink',
            description: 'cross-ref link',
        );

        $serialized = $link->jsonSerialize();

        self::assertSame('$ref', array_key_first($serialized));
        self::assertSame(['$ref', 'operationRef', 'description'], array_keys($serialized));
    }

    #[Test]
    public function json_serialize_includes_operation_id(): void
    {
        $link = new Link(
            operationId: 'getUserById',
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('operationId', $serialized);
    }

    #[Test]
    public function json_serialize_includes_request_body(): void
    {
        $link = new Link(
            requestBody: new RequestBody(
                description: 'Request body',
                content: new Content(
                    mediaTypes: ['application/json' => new MediaType()],
                ),
            ),
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('requestBody', $serialized);
    }

    #[Test]
    public function json_serialize_includes_server(): void
    {
        $link = new Link(
            server: new Server(
                url: 'https://api.example.com',
            ),
        );

        $serialized = $link->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('server', $serialized);
    }

    #[Test]
    public function json_serialize_emits_fields_in_wire_order(): void
    {
        $requestBody = new RequestBody(description: 'Body');
        $server = new Server(url: 'https://api.example.com');

        $link = new Link(
            operationRef: '#/operations/getUser',
            description: 'Description',
            operationId: 'getUser',
            parameters: ['id' => '$response.body#/id'],
            requestBody: $requestBody,
            server: $server,
        );

        $serialized = $link->jsonSerialize();

        self::assertSame([
            'operationRef' => '#/operations/getUser',
            'description' => 'Description',
            'operationId' => 'getUser',
            'parameters' => ['id' => '$response.body#/id'],
            'requestBody' => $requestBody,
            'server' => $server,
        ], $serialized);
    }
}
