<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Link
 */
final class LinkTest extends TestCase
{
    #[Test]
    public function can_create_link_with_all_fields(): void
    {
        $link = new \Duyler\OpenApi\Schema\Model\Link(
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
        $link = new \Duyler\OpenApi\Schema\Model\Link(
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
        $link = new \Duyler\OpenApi\Schema\Model\Link(
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
        $link = new \Duyler\OpenApi\Schema\Model\Link(
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
}
