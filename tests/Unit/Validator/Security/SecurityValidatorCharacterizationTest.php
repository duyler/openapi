<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Exception\UnsupportedSecuritySchemeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function array_filter;
use function array_values;

/**
 * Pins the current OR/AND security requirement semantics and the http subscheme
 * exception surface before the scheme handler registry migration.
 *
 * @internal
 */
final class SecurityValidatorCharacterizationTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function or_first_error_free_alternative_short_circuits_remaining_alternatives(): void
    {
        $spy = new CharacterizationSpyLogger();
        $validator = new SecurityValidator($spy);
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid-token');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
            ['OAuth2' => ['read']],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
            'OAuth2' => new SecurityScheme(type: 'oauth2'),
        ];

        $validator->validate(new SecurityValidationContext(
            request: $request,
            path: '/users',
            method: 'GET',
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        ));

        $scopeRecords = $this->scopeRecords($spy);
        $this->assertSame([], $scopeRecords, 'Later alternatives must not be evaluated after an error-free one.');
    }

    #[Test]
    public function or_errors_from_failing_alternatives_do_not_fail_operation_when_later_alternative_passes(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
            ['apiKeyHeader' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
            'apiKeyHeader' => new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key'),
        ];
        $request = $request->withHeader('X-API-Key', 'sk-123');

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
    public function or_unsupported_alternative_takes_precedence_over_accumulated_credential_errors(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
            ['unknownAuth' => ['read']],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
            'unknownAuth' => new SecurityScheme(type: 'unknownSchemeType'),
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
            $this->assertSame('unknownAuth', $e->schemeName);
            $this->assertSame('unknownSchemeType', $e->schemeType);
        }
    }

    #[Test]
    public function or_first_unsupported_alternative_wins_when_all_alternatives_unsupported(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['unknownAuth' => []],
            ['anotherUnknownAuth' => []],
        ]);
        $securitySchemes = [
            'unknownAuth' => new SecurityScheme(type: 'unknownSchemeType'),
            'anotherUnknownAuth' => new SecurityScheme(type: 'anotherUnknownSchemeType'),
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
            $this->assertSame('unknownAuth', $e->schemeName, 'The first unsupported alternative must win.');
        }
    }

    #[Test]
    public function and_reports_error_for_every_failing_scheme_in_requirement_order(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            [
                'bearerAuth' => [],
                'apiKeyHeader' => [],
            ],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
            'apiKeyHeader' => new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key'),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(2, $errors);
            $this->assertSame('bearerAuth', $errors[0]->schemeName(reveal: true));
            $this->assertSame('apiKeyHeader', $errors[1]->schemeName(reveal: true));
        }
    }

    #[Test]
    public function and_fails_when_any_single_conjunction_scheme_fails(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid-token');
        $securityRequirements = new SecurityRequirement([
            [
                'bearerAuth' => [],
                'apiKeyHeader' => [],
            ],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(type: 'http', scheme: 'bearer'),
            'apiKeyHeader' => new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key'),
        ];

        try {
            $validator->validate(new SecurityValidationContext(
                request: $request,
                path: '/users',
                method: 'GET',
                securityRequirements: $securityRequirements,
                securitySchemes: $securitySchemes,
            ));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertSame('apiKeyHeader', $errors[0]->schemeName(reveal: true));
        }
    }

    #[Test]
    public function http_non_bearer_subscheme_reports_http_scheme_parameter(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Negotiate some-token');
        $securityRequirements = new SecurityRequirement([
            ['negotiateAuth' => []],
        ]);
        $securitySchemes = [
            'negotiateAuth' => new SecurityScheme(type: 'http', scheme: 'negotiate'),
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
            $this->assertSame('negotiateAuth', $e->schemeName);
            $this->assertSame('http', $e->schemeType);
            $this->assertSame('negotiate', $e->httpScheme);
            $this->assertStringContainsString('http/negotiate', $e->getMessage());
        }
    }

    #[Test]
    public function http_uppercase_wire_subscheme_reports_lowercased_http_scheme_parameter(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['negotiateAuth' => []],
        ]);
        $securitySchemes = [
            'negotiateAuth' => new SecurityScheme(type: 'http', scheme: 'NEGOTIATE'),
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
            $this->assertSame('http', $e->schemeType);
            $this->assertSame('negotiate', $e->httpScheme);
        }
    }

    #[Test]
    public function http_type_without_declared_subscheme_defaults_to_bearer_validation(): void
    {
        $validator = new SecurityValidator();
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid-token');
        $securityRequirements = new SecurityRequirement([
            ['defaultHttp' => []],
        ]);
        $securitySchemes = [
            'defaultHttp' => new SecurityScheme(type: 'http'),
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

    /**
     * @param CharacterizationSpyLogger $spy
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function scopeRecords(CharacterizationSpyLogger $spy): array
    {
        return array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'Security requirement scopes' === $record['message'],
        ));
    }
}

/**
 * @internal
 */
final class CharacterizationSpyLogger implements LoggerInterface
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
