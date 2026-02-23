<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;
use Duyler\OpenApi\Schema\Model\SecurityScheme;

#[CoversClass(SecurityScheme::class)]
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
            openIdConnectUrl: null,
            oauth2MetadataUrl: null,
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
        $implicit = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            scopes: ['read' => 'Read access'],
        );
        $flows = new OAuthFlows(implicit: $implicit);

        $scheme = new SecurityScheme(
            type: 'oauth2',
            flows: $flows,
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('flows', $serialized);
        self::assertArrayHasKey('implicit', $serialized['flows']);
    }

    #[Test]
    public function json_serialize_includes_open_id_connect_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'openIdConnect',
            openIdConnectUrl: 'https://example.com/.well-known/openid-configuration',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openIdConnectUrl', $serialized);
    }

    #[Test]
    public function security_scheme_has_oauth2_metadata_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            oauth2MetadataUrl: 'https://auth.example.com/.well-known/oauth-authorization-server',
        );

        self::assertSame(
            'https://auth.example.com/.well-known/oauth-authorization-server',
            $scheme->oauth2MetadataUrl,
        );
    }

    #[Test]
    public function json_serialize_includes_oauth2_metadata_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            oauth2MetadataUrl: 'https://auth.example.com/.well-known/oauth-authorization-server',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('oauth2MetadataUrl', $serialized);
        self::assertSame(
            'https://auth.example.com/.well-known/oauth-authorization-server',
            $serialized['oauth2MetadataUrl'],
        );
    }

    #[Test]
    public function security_scheme_with_full_oauth_flows(): void
    {
        $authorizationCode = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access', 'write' => 'Write access'],
        );
        $deviceCode = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            deviceAuthorizationUrl: 'https://example.com/device/code',
            scopes: ['read' => 'Read access'],
            deprecated: false,
        );
        $flows = new OAuthFlows(
            authorizationCode: $authorizationCode,
            deviceCode: $deviceCode,
        );

        $scheme = new SecurityScheme(
            type: 'oauth2',
            description: 'OAuth 2.0 with Device Authorization Flow',
            flows: $flows,
            oauth2MetadataUrl: 'https://auth.example.com/.well-known/oauth-authorization-server',
        );

        self::assertSame('oauth2', $scheme->type);
        self::assertSame('OAuth 2.0 with Device Authorization Flow', $scheme->description);
        self::assertNotNull($scheme->flows);
        self::assertNotNull($scheme->flows->authorizationCode);
        self::assertNotNull($scheme->flows->deviceCode);
        self::assertSame(
            'https://auth.example.com/.well-known/oauth-authorization-server',
            $scheme->oauth2MetadataUrl,
        );
    }

    #[Test]
    public function security_scheme_with_deprecated_flow(): void
    {
        $implicit = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            scopes: ['read' => 'Read access'],
            deprecated: true,
        );
        $flows = new OAuthFlows(implicit: $implicit);

        $scheme = new SecurityScheme(
            type: 'oauth2',
            flows: $flows,
        );

        self::assertTrue($scheme->flows->implicit->deprecated);
    }

    #[Test]
    public function backward_compatible_flat_authorization_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            authorizationUrl: 'https://example.com/oauth/authorize',
        );

        self::assertSame('https://example.com/oauth/authorize', $scheme->authorizationUrl);
    }

    #[Test]
    public function backward_compatible_flat_token_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            tokenUrl: 'https://example.com/oauth/token',
        );

        self::assertSame('https://example.com/oauth/token', $scheme->tokenUrl);
    }

    #[Test]
    public function backward_compatible_flat_refresh_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            refreshUrl: 'https://example.com/oauth/refresh',
        );

        self::assertSame('https://example.com/oauth/refresh', $scheme->refreshUrl);
    }

    #[Test]
    public function backward_compatible_flat_scopes(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            scopes: ['read' => 'Read access', 'write' => 'Write access'],
        );

        self::assertSame(['read' => 'Read access', 'write' => 'Write access'], $scheme->scopes);
    }

    #[Test]
    public function json_serialize_includes_flat_authorization_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            authorizationUrl: 'https://example.com/oauth/authorize',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertArrayHasKey('authorizationUrl', $serialized);
        self::assertSame('https://example.com/oauth/authorize', $serialized['authorizationUrl']);
    }

    #[Test]
    public function json_serialize_includes_flat_token_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            tokenUrl: 'https://example.com/oauth/token',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertArrayHasKey('tokenUrl', $serialized);
        self::assertSame('https://example.com/oauth/token', $serialized['tokenUrl']);
    }

    #[Test]
    public function json_serialize_includes_flat_refresh_url(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            refreshUrl: 'https://example.com/oauth/refresh',
        );

        $serialized = $scheme->jsonSerialize();

        self::assertArrayHasKey('refreshUrl', $serialized);
    }

    #[Test]
    public function json_serialize_includes_flat_scopes(): void
    {
        $scheme = new SecurityScheme(
            type: 'oauth2',
            scopes: ['read' => 'Read access'],
        );

        $serialized = $scheme->jsonSerialize();

        self::assertArrayHasKey('scopes', $serialized);
        self::assertSame(['read' => 'Read access'], $serialized['scopes']);
    }
}
