<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;

#[CoversClass(OAuthFlows::class)]
final class OAuthFlowsTest extends TestCase
{
    #[Test]
    public function can_create_oauth_flows_with_implicit(): void
    {
        $implicit = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(implicit: $implicit);

        self::assertNotNull($flows->implicit);
        self::assertSame('https://example.com/oauth/authorize', $flows->implicit->authorizationUrl);
    }

    #[Test]
    public function can_create_oauth_flows_with_password(): void
    {
        $password = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(password: $password);

        self::assertNotNull($flows->password);
        self::assertSame('https://example.com/oauth/token', $flows->password->tokenUrl);
    }

    #[Test]
    public function can_create_oauth_flows_with_client_credentials(): void
    {
        $clientCredentials = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(clientCredentials: $clientCredentials);

        self::assertNotNull($flows->clientCredentials);
        self::assertSame('https://example.com/oauth/token', $flows->clientCredentials->tokenUrl);
    }

    #[Test]
    public function can_create_oauth_flows_with_authorization_code(): void
    {
        $authorizationCode = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(authorizationCode: $authorizationCode);

        self::assertNotNull($flows->authorizationCode);
        self::assertSame('https://example.com/oauth/authorize', $flows->authorizationCode->authorizationUrl);
        self::assertSame('https://example.com/oauth/token', $flows->authorizationCode->tokenUrl);
    }

    #[Test]
    public function can_create_oauth_flows_with_device_code(): void
    {
        $deviceCode = new OAuthFlow(
            tokenUrl: 'https://auth.example.com/token',
            deviceAuthorizationUrl: 'https://auth.example.com/device/code',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(deviceCode: $deviceCode);

        self::assertNotNull($flows->deviceCode);
        self::assertSame('https://auth.example.com/device/code', $flows->deviceCode->deviceAuthorizationUrl);
    }

    #[Test]
    public function json_serialize_includes_implicit(): void
    {
        $implicit = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(implicit: $implicit);

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('implicit', $serialized);
        self::assertArrayHasKey('authorizationUrl', $serialized['implicit']);
    }

    #[Test]
    public function json_serialize_includes_password(): void
    {
        $password = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(password: $password);

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('password', $serialized);
        self::assertArrayHasKey('tokenUrl', $serialized['password']);
    }

    #[Test]
    public function json_serialize_includes_client_credentials(): void
    {
        $clientCredentials = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(clientCredentials: $clientCredentials);

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('clientCredentials', $serialized);
    }

    #[Test]
    public function json_serialize_includes_authorization_code(): void
    {
        $authorizationCode = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(authorizationCode: $authorizationCode);

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('authorizationCode', $serialized);
    }

    #[Test]
    public function json_serialize_includes_device_code(): void
    {
        $deviceCode = new OAuthFlow(
            tokenUrl: 'https://auth.example.com/token',
            deviceAuthorizationUrl: 'https://auth.example.com/device/code',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(deviceCode: $deviceCode);

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('deviceCode', $serialized);
        self::assertArrayHasKey('deviceAuthorizationUrl', $serialized['deviceCode']);
    }

    #[Test]
    public function json_serialize_excludes_null_flows(): void
    {
        $flows = new OAuthFlows();

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('implicit', $serialized);
        self::assertArrayNotHasKey('password', $serialized);
        self::assertArrayNotHasKey('clientCredentials', $serialized);
        self::assertArrayNotHasKey('authorizationCode', $serialized);
        self::assertArrayNotHasKey('deviceCode', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_flows(): void
    {
        $implicit = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            scopes: ['read' => 'Read access'],
        );
        $password = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );
        $clientCredentials = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );
        $authorizationCode = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );
        $deviceCode = new OAuthFlow(
            tokenUrl: 'https://auth.example.com/token',
            deviceAuthorizationUrl: 'https://auth.example.com/device/code',
            scopes: ['read' => 'Read access'],
        );

        $flows = new OAuthFlows(
            implicit: $implicit,
            password: $password,
            clientCredentials: $clientCredentials,
            authorizationCode: $authorizationCode,
            deviceCode: $deviceCode,
        );

        $serialized = $flows->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('implicit', $serialized);
        self::assertArrayHasKey('password', $serialized);
        self::assertArrayHasKey('clientCredentials', $serialized);
        self::assertArrayHasKey('authorizationCode', $serialized);
        self::assertArrayHasKey('deviceCode', $serialized);
    }
}
