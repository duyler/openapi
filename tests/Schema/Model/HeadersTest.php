<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Headers
 */
final class HeadersTest extends TestCase
{
    #[Test]
    public function can_create_headers(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: 'Header',
            required: false,
            deprecated: false,
            schema: $schema,
        );

        $headers = new Headers(
            headers: ['X-Custom-Header' => $header],
        );

        self::assertArrayHasKey('X-Custom-Header', $headers->headers);
        self::assertInstanceOf(Header::class, $headers->headers['X-Custom-Header']);
    }

    #[Test]
    public function can_create_empty_headers(): void
    {
        $headers = new Headers(
            headers: [],
        );

        self::assertCount(0, $headers->headers);
    }

    #[Test]
    public function json_serialize_includes_headers(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $header = new Header(
            description: 'Header',
            required: false,
            deprecated: false,
            schema: $schema,
        );

        $headers = new Headers(
            headers: ['X-Custom-Header' => $header],
        );

        $serialized = $headers->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('X-Custom-Header', $serialized);
        self::assertIsArray($serialized['X-Custom-Header']);
        self::assertArrayHasKey('schema', $serialized['X-Custom-Header']);
    }
}
