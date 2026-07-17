<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\SecuritySchemeBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SecuritySchemeBuilderTest extends TestCase
{
    private SecuritySchemeBuilder $securitySchemeBuilder;

    protected function setUp(): void
    {
        $this->securitySchemeBuilder = (new OpenApiBuildContext())->securitySchemeBuilder;
    }

    #[Test]
    public function build_security_scheme_throws_when_type_missing(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Security scheme must have type');

        $this->securitySchemeBuilder->buildSecurityScheme(['description' => 'no type']);
    }

    #[Test]
    public function build_security_scheme_http_bearer(): void
    {
        $scheme = $this->securitySchemeBuilder->buildSecurityScheme([
            'type' => 'http',
            'description' => 'Bearer token auth',
            'scheme' => 'bearer',
            'bearerFormat' => 'JWT',
        ]);

        self::assertInstanceOf(SecurityScheme::class, $scheme);
        self::assertSame('http', $scheme->type);
        self::assertSame('Bearer token auth', $scheme->description);
        self::assertSame('bearer', $scheme->scheme);
        self::assertSame('JWT', $scheme->bearerFormat);
    }

    #[Test]
    public function build_security_scheme_api_key(): void
    {
        $scheme = $this->securitySchemeBuilder->buildSecurityScheme([
            'type' => 'apiKey',
            'name' => 'X-API-Key',
            'in' => 'header',
        ]);

        self::assertSame('apiKey', $scheme->type);
        self::assertSame('X-API-Key', $scheme->name);
        self::assertSame('header', $scheme->in);
    }

    #[Test]
    public function build_security_scheme_oauth2_with_flows(): void
    {
        $scheme = $this->securitySchemeBuilder->buildSecurityScheme([
            'type' => 'oauth2',
            'flows' => [
                'authorizationCode' => [
                    'authorizationUrl' => 'https://example.com/oauth/authorize',
                    'tokenUrl' => 'https://example.com/oauth/token',
                    'scopes' => ['read' => 'read access', 'write' => 'write access'],
                ],
                'clientCredentials' => [
                    'tokenUrl' => 'https://example.com/oauth/token',
                    'scopes' => [],
                ],
            ],
        ]);

        self::assertSame('oauth2', $scheme->type);
        self::assertInstanceOf(OAuthFlows::class, $scheme->flows);
        self::assertInstanceOf(OAuthFlow::class, $scheme->flows->authorizationCode);
        self::assertSame('https://example.com/oauth/authorize', $scheme->flows->authorizationCode->authorizationUrl);
        self::assertSame(['read', 'write'], array_keys($scheme->flows->authorizationCode->scopes ?? []));
        self::assertInstanceOf(OAuthFlow::class, $scheme->flows->clientCredentials);
        self::assertNull($scheme->flows->implicit);
        self::assertNull($scheme->flows->password);
    }

    #[Test]
    public function build_security_scheme_open_id_connect(): void
    {
        $scheme = $this->securitySchemeBuilder->buildSecurityScheme([
            'type' => 'openIdConnect',
            'openIdConnectUrl' => 'https://example.com/.well-known/openid-configuration',
        ]);

        self::assertSame('openIdConnect', $scheme->type);
        self::assertSame('https://example.com/.well-known/openid-configuration', $scheme->openIdConnectUrl);
    }

    #[Test]
    public function build_oauth_flows_with_all_flow_types(): void
    {
        $flows = $this->securitySchemeBuilder->buildOAuthFlows([
            'implicit' => [
                'authorizationUrl' => 'https://example.com/auth',
                'scopes' => ['read' => 'read'],
            ],
            'password' => [
                'tokenUrl' => 'https://example.com/token',
                'scopes' => [],
            ],
            'clientCredentials' => [
                'tokenUrl' => 'https://example.com/token',
                'scopes' => [],
            ],
            'authorizationCode' => [
                'authorizationUrl' => 'https://example.com/auth',
                'tokenUrl' => 'https://example.com/token',
                'scopes' => [],
            ],
            'deviceCode' => [
                'deviceAuthorizationUrl' => 'https://example.com/device',
                'tokenUrl' => 'https://example.com/token',
                'scopes' => [],
                'deprecated' => true,
            ],
        ]);

        self::assertInstanceOf(OAuthFlows::class, $flows);
        self::assertNotNull($flows->implicit);
        self::assertNotNull($flows->password);
        self::assertNotNull($flows->clientCredentials);
        self::assertNotNull($flows->authorizationCode);
        self::assertNotNull($flows->deviceCode);
        self::assertSame('https://example.com/device', $flows->deviceCode->deviceAuthorizationUrl);
        self::assertTrue($flows->deviceCode->deprecated);
    }

    #[Test]
    public function build_oauth_flows_empty_returns_object_with_nulls(): void
    {
        $flows = $this->securitySchemeBuilder->buildOAuthFlows([]);

        self::assertNull($flows->implicit);
        self::assertNull($flows->password);
        self::assertNull($flows->clientCredentials);
        self::assertNull($flows->authorizationCode);
        self::assertNull($flows->deviceCode);
    }

    #[Test]
    public function build_oauth_flow_with_full_fields(): void
    {
        $flow = $this->securitySchemeBuilder->buildOAuthFlow([
            'authorizationUrl' => 'https://example.com/auth',
            'tokenUrl' => 'https://example.com/token',
            'refreshUrl' => 'https://example.com/refresh',
            'scopes' => ['read' => 'read', 'write' => 'write'],
            'deviceAuthorizationUrl' => 'https://example.com/device',
            'deprecated' => false,
        ]);

        self::assertSame('https://example.com/auth', $flow->authorizationUrl);
        self::assertSame('https://example.com/token', $flow->tokenUrl);
        self::assertSame('https://example.com/refresh', $flow->refreshUrl);
        self::assertSame(['read' => 'read', 'write' => 'write'], $flow->scopes);
        self::assertSame('https://example.com/device', $flow->deviceAuthorizationUrl);
        self::assertFalse($flow->deprecated);
    }
}
