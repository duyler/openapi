<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\SecurityScheme;

/**
 * @covers \Duyler\OpenApi\Schema\Model\SecurityScheme
 */
final class SecuritySchemeTest extends TestCase
{
    #[Test]
    public function can_create_security_scheme(): void
    {
        $scheme = new SecurityScheme(
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
        $scheme = new SecurityScheme(
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
        $scheme = new SecurityScheme(
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
        $scheme = new SecurityScheme(
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

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $scheme = new SecurityScheme(
            type: 'http',
            scheme: 'bearer',
            bearerFormat: 'JWT',
            description: 'Bearer auth',
            name: null,
            in: null,
            flows: null,
            authorizationUrl: null,
            tokenUrl: null,
            refreshUrl: null,
            scopes: null,
            openIdConnectUrl: null,
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayHasKey('scheme', $serialized);
        self::assertArrayHasKey('bearerFormat', $serialized);
        self::assertArrayHasKey('description', $serialized);
    }

    #[Test]
    public function json_serialize_includes_name(): void
    {
        $scheme = new SecurityScheme(
            type: 'apiKey',
            name: 'X-API-Key',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
    }

    #[Test]
    public function json_serialize_includes_in(): void
    {
        $scheme = new SecurityScheme(
            type: 'apiKey',
            in: 'header',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('in', $serialized);
    }

    #[Test]
    public function json_serialize_includes_flows(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            flows: 'implicit',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('flows', $serialized);
    }

    #[Test]
    public function json_serialize_includes_authorizationUrl(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            authorizationUrl: 'https://example.com/oauth/authorize',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('authorizationUrl', $serialized);
    }

    #[Test]
    public function json_serialize_includes_tokenUrl(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            tokenUrl: 'https://example.com/oauth/token',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('tokenUrl', $serialized);
    }

    #[Test]
    public function json_serialize_includes_refreshUrl(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            refreshUrl: 'https://example.com/oauth/refresh',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('refreshUrl', $serialized);
    }

    #[Test]
    public function json_serialize_includes_scopes(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            scopes: ['read', 'write'],
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('scopes', $serialized);
    }

    #[Test]
    public function json_serialize_includes_openIdConnectUrl(): void
    {
        $scheme = new SecurityScheme(
            type: 'openIdConnect',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openIdConnectUrl', $serialized);
    }
}
