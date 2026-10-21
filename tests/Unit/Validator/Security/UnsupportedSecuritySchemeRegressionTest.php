<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Exception\UnsupportedSecuritySchemeException;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class UnsupportedSecuritySchemeRegressionTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function oauth2_scheme_throws_unsupported_exception(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer some-token');
        $securityRequirements = new SecurityRequirement([
            ['OAuth2' => ['read']],
        ]);
        $securitySchemes = [
            'OAuth2' => new SecurityScheme(
                type: 'oauth2',
            ),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException was not thrown');
        } catch (UnsupportedSecuritySchemeException $e) {
            $this->assertSame('OAuth2', $e->schemeName);
            $this->assertSame('oauth2', $e->schemeType);
            $this->assertNull($e->httpScheme);
        }
    }

    #[Test]
    public function open_id_connect_scheme_throws_unsupported_exception(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer some-token');
        $securityRequirements = new SecurityRequirement([
            ['OpenIDConnect' => ['openid']],
        ]);
        $securitySchemes = [
            'OpenIDConnect' => new SecurityScheme(
                type: 'openIdConnect',
                openIdConnectUrl: 'https://idp.example.com/.well-known/openid-configuration',
            ),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException was not thrown');
        } catch (UnsupportedSecuritySchemeException $e) {
            $this->assertSame('OpenIDConnect', $e->schemeName);
            $this->assertSame('openIdConnect', $e->schemeType);
            $this->assertNull($e->httpScheme);
        }
    }

    #[Test]
    public function http_digest_scheme_throws_unsupported_exception_with_http_scheme_descriptor(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['digestAuth' => []],
        ]);
        $securitySchemes = [
            'digestAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'digest',
            ),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException was not thrown');
        } catch (UnsupportedSecuritySchemeException $e) {
            $this->assertSame('digestAuth', $e->schemeName);
            $this->assertSame('http', $e->schemeType);
            $this->assertSame('digest', $e->httpScheme);
        }
    }

    #[Test]
    public function mutual_tls_scheme_throws_unsupported_exception(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['mutualTLS' => []],
        ]);
        $securitySchemes = [
            'mutualTLS' => new SecurityScheme(
                type: 'mutualTLS',
            ),
        ];

        $this->expectException(UnsupportedSecuritySchemeException::class);

        $validator->validate(new SecurityValidationContext(
            request: $request,
            path: '/users',
            method: 'GET',
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        ));
    }

    #[Test]
    public function and_list_with_unsupported_scheme_fails_closed_even_when_supported_sibling_passes(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid-token');
        $securityRequirements = new SecurityRequirement([
            [
                'OAuth2' => ['read'],
                'bearerAuth' => [],
            ],
        ]);
        $securitySchemes = [
            'OAuth2' => new SecurityScheme(type: 'oauth2'),
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException for AND list with unsupported scheme');
        } catch (UnsupportedSecuritySchemeException $e) {
            $this->assertSame('OAuth2', $e->schemeName);
            $this->assertSame('oauth2', $e->schemeType);
        }
    }

    #[Test]
    public function or_list_succeeds_when_supported_alternative_passes_and_unsupported_sibling_skipped(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid-token');
        $securityRequirements = new SecurityRequirement([
            ['OAuth2' => ['read']],
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'OAuth2' => new SecurityScheme(type: 'oauth2'),
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
        ];

        $validator->validate(new SecurityValidationContext(
            request: $request,
            path: '/users',
            method: 'GET',
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        ));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function or_list_rethrows_unsupported_exception_when_no_alternative_succeeds(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['OAuth2' => ['read']],
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'OAuth2' => new SecurityScheme(type: 'oauth2'),
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException when no OR alternative succeeds');
        } catch (UnsupportedSecuritySchemeException $e) {
            $this->assertSame('OAuth2', $e->schemeName);
            $this->assertSame('oauth2', $e->schemeType);
        }
    }

    #[Test]
    public function oauth2_scopes_are_logged_at_debug_level_for_diagnostic_purposes(): void
    {
        $spy = new ScopeSpyLogger();
        $validator = new SecurityValidator($spy);
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer irrelevant');
        $securityRequirements = new SecurityRequirement([
            ['OAuth2' => ['read', 'write']],
        ]);
        $securitySchemes = [
            'OAuth2' => new SecurityScheme(type: 'oauth2'),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException was not thrown');
        } catch (UnsupportedSecuritySchemeException) {
            $scopeRecords = array_values(array_filter(
                $spy->records,
                static fn(array $record): bool => 'Security requirement scopes' === $record['message'],
            ));

            $this->assertCount(1, $scopeRecords, 'scopes must be logged even when scheme is unsupported');
            $context = $scopeRecords[0]['context'];
            $this->assertSame('OAuth2', $context['schemeName']);
            $this->assertSame('oauth2', $context['schemeType']);
            $this->assertSame(['read', 'write'], $context['scopes']);
        }
    }

    #[Test]
    public function empty_scopes_do_not_trigger_scopes_debug_log_entry(): void
    {
        $spy = new ScopeSpyLogger();
        $validator = new SecurityValidator($spy);
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid-token');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
        ];

        $validator->validate(new SecurityValidationContext(
            request: $request,
            path: '/users',
            method: 'GET',
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        ));

        $scopeRecords = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'Security requirement scopes' === $record['message'],
        ));
        $this->assertSame([], $scopeRecords);
    }

    #[Test]
    public function to_string_returns_message_only_without_paths_or_trace(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['OAuth2' => []],
        ]);
        $securitySchemes = [
            'OAuth2' => new SecurityScheme(type: 'oauth2'),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected UnsupportedSecuritySchemeException was not thrown');
        } catch (UnsupportedSecuritySchemeException $e) {
            $string = (string) $e;
            $this->assertSame($e->getMessage(), $string);
            $this->assertStringNotContainsString('/Users/', $string);
            $this->assertStringNotContainsString('Stack trace', $string);
            $this->assertStringNotContainsString('#0 ', $string);
        }
    }
}

/**
 * @internal
 */
final class ScopeSpyLogger implements LoggerInterface
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
