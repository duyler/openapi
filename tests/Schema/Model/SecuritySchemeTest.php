<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Duyler\OpenApi\Schema\Model\SecurityScheme
 */
final class SecuritySchemeTest extends TestCase
{
    #[Test]
    public function can_create_security_scheme(): void
    {
        $scheme = new \Duyler\OpenApi\Schema\Model\SecurityScheme(
            type: 'http',
            scheme: 'bearer',
            bearerFormat: null,
            description: 'Bearer auth',
        );

        self::assertSame('http', $scheme->type);
        self::assertSame('bearer', $scheme->scheme);
        self::assertNull($scheme->bearerFormat);
        self::assertSame('Bearer auth', $scheme->description);
    }

    #[Test]
    public function can_create_api_key_scheme(): void
    {
        $scheme = new \Duyler\OpenApi\Schema\Model\SecurityScheme(
            type: 'apiKey',
            scheme: null,
            bearerFormat: null,
            description: 'API key authentication',
        );

        self::assertSame('apiKey', $scheme->type);
        self::assertNull($scheme->scheme);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $scheme = new \Duyler\OpenApi\Schema\Model\SecurityScheme(
            type: 'http',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Bearer auth',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayHasKey('scheme', $serialized);
        self::assertSame('http', $serialized['type']);
        self::assertSame('bearer', $serialized['scheme']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $scheme = new \Duyler\OpenApi\Schema\Model\SecurityScheme(
            type: 'apiKey',
            scheme: null,
            bearerFormat: null,
            description: 'API key',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayNotHasKey('scheme', $serialized);
        self::assertArrayNotHasKey('bearerFormat', $serialized);
    }
}
