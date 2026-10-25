<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Security\Internal\BearerPresenceValidator;
use Duyler\OpenApi\Validator\Security\Internal\OAuth2Handler;
use Duyler\OpenApi\Validator\Security\Internal\OpenIdConnectHandler;
use Duyler\OpenApi\Validator\Security\Internal\SchemeCredentialReporter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function str_repeat;

final class OAuth2OpenIdConnectHandlerTest extends TestCase
{
    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function oauth2_handler_accepts_only_oauth2_scheme(): void
    {
        $handler = $this->makeOAuth2Handler();

        $this->assertTrue($handler->supports(new SecurityScheme(type: 'oauth2')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'openIdConnect')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'bearer')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'mutualTLS')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key')));
    }

    #[Test]
    public function open_id_connect_handler_accepts_only_open_id_connect_scheme(): void
    {
        $handler = $this->makeOpenIdConnectHandler();

        $this->assertTrue($handler->supports(new SecurityScheme(type: 'openIdConnect')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'oauth2')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'bearer')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'mutualTLS')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key')));
    }

    #[Test]
    public function oauth2_handler_passes_with_bearer_token_without_declared_scopes(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            new SecurityScheme(type: 'oauth2'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertSame([], $spy->records);
    }

    #[Test]
    public function oauth2_handler_reports_missing_credentials_without_authorization_header(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);

        $error = $handler->validate(
            $this->makeRequest(null),
            new SecurityScheme(type: 'oauth2'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('oauth2Auth', $error->schemeName(reveal: true));
        $this->assertSame('oauth2', $error->schemeType(reveal: true));
        $this->assertSame('Authorization header', $error->location(reveal: true));

        $scopeRecords = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'Security scheme declared scopes' === $record['message'],
        ));
        $this->assertSame([], $scopeRecords);
    }

    #[Test]
    public function oauth2_handler_reports_missing_credentials_for_non_bearer_authorization_header(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);

        $error = $handler->validate(
            $this->makeRequest('Basic dXNlcjpwYXNz'),
            new SecurityScheme(type: 'oauth2'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('oauth2', $error->schemeType(reveal: true));
    }

    #[Test]
    public function oauth2_handler_logs_union_of_declared_flow_scopes_and_flat_scopes(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $scheme = new SecurityScheme(
            type: 'oauth2',
            flows: new OAuthFlows(
                implicit: new OAuthFlow(scopes: ['read' => 'Read access', 'write' => 'Write access']),
                password: new OAuthFlow(scopes: ['read' => 'Password read']),
                clientCredentials: new OAuthFlow(scopes: ['admin' => 'Admin access']),
                authorizationCode: new OAuthFlow(scopes: ['profile' => 'Profile access']),
                deviceAuthorization: new OAuthFlow(scopes: ['device' => 'Device access']),
            ),
            scopes: ['offline' => 'Offline access', 'write' => 'Flat write'],
        );

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            $scheme,
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertSame([
            [
                'level' => 'debug',
                'message' => 'Security scheme declared scopes',
                'context' => [
                    'schemeName' => 'oauth2Auth',
                    'schemeType' => 'oauth2',
                    'scopes' => [
                        'read' => 'Read access',
                        'write' => 'Write access',
                        'admin' => 'Admin access',
                        'profile' => 'Profile access',
                        'device' => 'Device access',
                        'offline' => 'Offline access',
                    ],
                ],
            ],
        ], $spy->records);
    }

    #[Test]
    public function oauth2_handler_treats_empty_flow_scopes_maps_as_absent(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $scheme = new SecurityScheme(
            type: 'oauth2',
            flows: new OAuthFlows(
                implicit: new OAuthFlow(scopes: []),
                password: new OAuthFlow(scopes: []),
            ),
        );

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            $scheme,
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertSame([], $spy->records);
    }

    #[Test]
    public function oauth2_handler_reports_invalid_metadata_url_even_with_bearer_token(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $scheme = new SecurityScheme(
            type: 'oauth2',
            oauth2MetadataUrl: 'not a uri',
        );

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            $scheme,
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('oauth2Auth', $error->schemeName(reveal: true));
        $this->assertSame('oauth2', $error->schemeType(reveal: true));
        $this->assertSame(
            'Security scheme oauth2MetadataUrl must be a valid absolute URI',
            $error->location(reveal: true),
        );

        $scopeRecords = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'Security scheme declared scopes' === $record['message'],
        ));
        $this->assertSame([], $scopeRecords);
    }

    #[Test]
    public function oauth2_handler_checks_metadata_url_before_bearer_presence(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $scheme = new SecurityScheme(
            type: 'oauth2',
            oauth2MetadataUrl: 'not a uri',
        );

        $error = $handler->validate(
            $this->makeRequest(null),
            $scheme,
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame(
            'Security scheme oauth2MetadataUrl must be a valid absolute URI',
            $error->location(reveal: true),
        );
    }

    #[Test]
    public function open_id_connect_handler_checks_open_id_connect_url_before_bearer_presence(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOpenIdConnectHandler($spy);
        $scheme = new SecurityScheme(
            type: 'openIdConnect',
            openIdConnectUrl: 'not a uri',
        );

        $error = $handler->validate(
            $this->makeRequest(null),
            $scheme,
            $this->makeReporter($spy, 'openIdConnectAuth'),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame(
            'Security scheme openIdConnectUrl must be a valid absolute URI',
            $error->location(reveal: true),
        );
    }

    #[Test]
    public function oauth2_handler_passes_with_valid_metadata_url(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $scheme = new SecurityScheme(
            type: 'oauth2',
            oauth2MetadataUrl: 'https://auth.example.com/.well-known/oauth-authorization-server',
        );

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            $scheme,
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
    }

    #[Test]
    public function oauth2_handler_survives_adversarial_authorization_header_without_preg_exception(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $adversarial = 'Bearer ' . str_repeat('a', 15000) . ' !';

        $error = $handler->validate(
            $this->makeRequest($adversarial),
            new SecurityScheme(type: 'oauth2'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('oauth2', $error->schemeType(reveal: true));
    }

    #[Test]
    public function oauth2_handler_survives_whitespace_padded_bearer_token_without_preg_exception(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $adversarial = 'Bearer tok' . str_repeat(' ', 20000) . '!';

        $error = $handler->validate(
            $this->makeRequest($adversarial),
            new SecurityScheme(type: 'oauth2'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('oauth2', $error->schemeType(reveal: true));
        $this->assertSame('Authorization header', $error->location(reveal: true));
    }

    #[Test]
    public function open_id_connect_handler_reports_invalid_open_id_connect_url(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOpenIdConnectHandler($spy);
        $scheme = new SecurityScheme(
            type: 'openIdConnect',
            openIdConnectUrl: 'not a uri',
        );

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            $scheme,
            $this->makeReporter($spy, 'openIdConnectAuth'),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('openIdConnectAuth', $error->schemeName(reveal: true));
        $this->assertSame('openIdConnect', $error->schemeType(reveal: true));
        $this->assertSame(
            'Security scheme openIdConnectUrl must be a valid absolute URI',
            $error->location(reveal: true),
        );

        $scopeRecords = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'Security scheme declared scopes' === $record['message'],
        ));
        $this->assertSame([], $scopeRecords);
    }

    #[Test]
    public function open_id_connect_handler_skips_url_check_when_url_is_absent(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOpenIdConnectHandler($spy);

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            new SecurityScheme(type: 'openIdConnect'),
            $this->makeReporter($spy, 'openIdConnectAuth'),
        );

        $this->assertNull($error);
    }

    #[Test]
    public function open_id_connect_handler_passes_with_valid_url_and_bearer_token(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOpenIdConnectHandler($spy);
        $scheme = new SecurityScheme(
            type: 'openIdConnect',
            openIdConnectUrl: 'https://idp.example.com/.well-known/openid-configuration',
            scopes: ['openid' => 'OpenID scope'],
        );

        $error = $handler->validate(
            $this->makeRequest('Bearer valid-token'),
            $scheme,
            $this->makeReporter($spy, 'openIdConnectAuth'),
        );

        $this->assertNull($error);
        $this->assertSame([
            [
                'level' => 'debug',
                'message' => 'Security scheme declared scopes',
                'context' => [
                    'schemeName' => 'openIdConnectAuth',
                    'schemeType' => 'openIdConnect',
                    'scopes' => ['openid' => 'OpenID scope'],
                ],
            ],
        ], $spy->records);
    }

    #[Test]
    public function open_id_connect_handler_reports_missing_bearer_token_with_valid_url(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOpenIdConnectHandler($spy);
        $scheme = new SecurityScheme(
            type: 'openIdConnect',
            openIdConnectUrl: 'https://idp.example.com/.well-known/openid-configuration',
        );

        $error = $handler->validate(
            $this->makeRequest(null),
            $scheme,
            $this->makeReporter($spy, 'openIdConnectAuth'),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('openIdConnect', $error->schemeType(reveal: true));
        $this->assertSame('Authorization header', $error->location(reveal: true));
    }

    #[Test]
    public function handlers_never_log_token_values(): void
    {
        $spy = new DeclaredScopesSpyLogger();
        $handler = $this->makeOAuth2Handler($spy);
        $token = 'secret-jwt-token-value';

        $error = $handler->validate(
            $this->makeRequest('Bearer ' . $token),
            new SecurityScheme(type: 'oauth2'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertSame([], $spy->records, 'The bearer presence path must not log token values.');
    }

    private function makeOAuth2Handler(LoggerInterface $logger = new NullLogger()): OAuth2Handler
    {
        return new OAuth2Handler(new BearerPresenceValidator(new PregExecutor(), $logger));
    }

    private function makeOpenIdConnectHandler(LoggerInterface $logger = new NullLogger()): OpenIdConnectHandler
    {
        return new OpenIdConnectHandler(new BearerPresenceValidator(new PregExecutor(), $logger));
    }

    private function makeReporter(LoggerInterface $logger, string $schemeName = 'oauth2Auth'): SchemeCredentialReporter
    {
        return new SchemeCredentialReporter($schemeName, $logger);
    }

    private function makeRequest(?string $authorization): ServerRequestInterface
    {
        $request = $this->factory->createServerRequest('GET', '/users');

        if (null !== $authorization) {
            $request = $request->withHeader('Authorization', $authorization);
        }

        return $request;
    }
}

/**
 * @internal
 */
final class DeclaredScopesSpyLogger implements LoggerInterface
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function emergency($message, array $context = []): void
    {
        $this->records[] = ['level' => 'emergency', 'message' => $message, 'context' => $context];
    }

    public function alert($message, array $context = []): void
    {
        $this->records[] = ['level' => 'alert', 'message' => $message, 'context' => $context];
    }

    public function critical($message, array $context = []): void
    {
        $this->records[] = ['level' => 'critical', 'message' => $message, 'context' => $context];
    }

    public function error($message, array $context = []): void
    {
        $this->records[] = ['level' => 'error', 'message' => $message, 'context' => $context];
    }

    public function warning($message, array $context = []): void
    {
        $this->records[] = ['level' => 'warning', 'message' => $message, 'context' => $context];
    }

    public function notice($message, array $context = []): void
    {
        $this->records[] = ['level' => 'notice', 'message' => $message, 'context' => $context];
    }

    public function info($message, array $context = []): void
    {
        $this->records[] = ['level' => 'info', 'message' => $message, 'context' => $context];
    }

    public function debug($message, array $context = []): void
    {
        $this->records[] = ['level' => 'debug', 'message' => $message, 'context' => $context];
    }

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => $message, 'context' => $context];
    }
}
