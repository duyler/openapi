<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\OAuthFlow;

#[CoversClass(OAuthFlow::class)]
final class OAuthFlowTest extends TestCase
{
    #[Test]
    public function can_create_oauth_flow_with_authorization_url(): void
    {
        $flow = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access', 'write' => 'Write access'],
        );

        self::assertSame('https://example.com/oauth/authorize', $flow->authorizationUrl);
        self::assertSame('https://example.com/oauth/token', $flow->tokenUrl);
        self::assertSame(['read' => 'Read access', 'write' => 'Write access'], $flow->scopes);
    }

    #[Test]
    public function can_create_oauth_flow_with_device_authorization_url(): void
    {
        $flow = new OAuthFlow(
            tokenUrl: 'https://auth.example.com/token',
            deviceAuthorizationUrl: 'https://auth.example.com/device/code',
            scopes: ['read' => 'Read access'],
        );

        self::assertSame('https://auth.example.com/device/code', $flow->deviceAuthorizationUrl);
    }

    #[Test]
    public function can_create_oauth_flow_with_deprecated_flag(): void
    {
        $flow = new OAuthFlow(
            tokenUrl: 'https://auth.example.com/token',
            scopes: ['read' => 'Read'],
            deprecated: true,
        );

        self::assertTrue($flow->deprecated);
    }

    #[Test]
    public function can_create_oauth_flow_with_refresh_url(): void
    {
        $flow = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            refreshUrl: 'https://example.com/oauth/refresh',
            scopes: ['read' => 'Read access'],
        );

        self::assertSame('https://example.com/oauth/refresh', $flow->refreshUrl);
    }

    #[Test]
    public function json_serialize_includes_authorization_url(): void
    {
        $flow = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            scopes: ['read' => 'Read access'],
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('authorizationUrl', $serialized);
        self::assertSame('https://example.com/oauth/authorize', $serialized['authorizationUrl']);
    }

    #[Test]
    public function json_serialize_includes_token_url(): void
    {
        $flow = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('tokenUrl', $serialized);
        self::assertSame('https://example.com/oauth/token', $serialized['tokenUrl']);
    }

    #[Test]
    public function json_serialize_includes_refresh_url(): void
    {
        $flow = new OAuthFlow(
            refreshUrl: 'https://example.com/oauth/refresh',
            scopes: ['read' => 'Read access'],
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('refreshUrl', $serialized);
    }

    #[Test]
    public function json_serialize_includes_scopes(): void
    {
        $flow = new OAuthFlow(
            scopes: ['read' => 'Read access', 'write' => 'Write access'],
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('scopes', $serialized);
        self::assertSame(['read' => 'Read access', 'write' => 'Write access'], $serialized['scopes']);
    }

    #[Test]
    public function json_serialize_includes_device_authorization_url(): void
    {
        $flow = new OAuthFlow(
            tokenUrl: 'https://auth.example.com/token',
            deviceAuthorizationUrl: 'https://auth.example.com/device/code',
            scopes: ['read' => 'Read access'],
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('deviceAuthorizationUrl', $serialized);
        self::assertSame('https://auth.example.com/device/code', $serialized['deviceAuthorizationUrl']);
    }

    #[Test]
    public function json_serialize_includes_deprecated(): void
    {
        $flow = new OAuthFlow(
            scopes: ['read' => 'Read access'],
            deprecated: true,
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('deprecated', $serialized);
        self::assertTrue($serialized['deprecated']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $flow = new OAuthFlow(
            tokenUrl: 'https://example.com/oauth/token',
            scopes: ['read' => 'Read access'],
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('authorizationUrl', $serialized);
        self::assertArrayNotHasKey('refreshUrl', $serialized);
        self::assertArrayNotHasKey('deviceAuthorizationUrl', $serialized);
        self::assertArrayNotHasKey('deprecated', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $flow = new OAuthFlow(
            authorizationUrl: 'https://example.com/oauth/authorize',
            tokenUrl: 'https://example.com/oauth/token',
            refreshUrl: 'https://example.com/oauth/refresh',
            scopes: ['read' => 'Read access'],
            deviceAuthorizationUrl: 'https://example.com/device/code',
            deprecated: false,
        );

        $serialized = $flow->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('authorizationUrl', $serialized);
        self::assertArrayHasKey('tokenUrl', $serialized);
        self::assertArrayHasKey('refreshUrl', $serialized);
        self::assertArrayHasKey('scopes', $serialized);
        self::assertArrayHasKey('deviceAuthorizationUrl', $serialized);
        self::assertArrayHasKey('deprecated', $serialized);
    }
}
