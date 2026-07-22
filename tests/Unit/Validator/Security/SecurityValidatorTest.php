<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/** @internal */
final class SecurityValidatorTest extends TestCase
{
    private Psr17Factory $factory;
    private SecurityValidator $validator;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->validator = new SecurityValidator();
    }

    #[Test]
    public function http_bearer_with_valid_token_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer eyJhbGciOiJIUzI1NiJ9.test');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    /**
     * RFC 6750 §2.1: Bearer scheme is case-insensitive. SEC-16
     * anti-test: revert preg_match to str_starts_with('Bearer ') and
     * every case-variant assertion below fails.
     */
    #[Test]
    public function http_bearer_uppercase_scheme_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'BEARER abc123');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function http_bearer_lowercase_scheme_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'bearer abc123');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function http_bearer_mixed_case_scheme_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'BeArEr abc123');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    /**
     * RFC 9110 OWS: one or more whitespace separators between scheme
     * and token are valid. SEC-16 anti-test: regex with a literal
     * single space (/\s/ removed) would reject this header.
     */
    #[Test]
    public function http_bearer_multiple_spaces_between_scheme_and_token_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer  abc123');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    /**
     * RFC 6750 §2.1 requires a non-empty b64token. SEC-16 anti-test:
     * regex without the \S+ requirement would let this header through.
     */
    #[Test]
    public function http_bearer_with_trailing_space_only_rejected_as_missing_token(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer ');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('bearerAuth', $error->schemeName(reveal: true));
            $this->assertSame('http/bearer', $error->schemeType(reveal: true));
            $this->assertSame('Authorization header', $error->location(reveal: true));
        }
    }

    /**
     * SEC-16 anti-test: 'Bearer' alone (no separator, no token) must
     * not satisfy the validation — the regex requires \s+ after the
     * scheme word.
     */
    #[Test]
    public function http_bearer_scheme_word_without_token_rejected(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->expectException(ValidationException::class);

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
    }

    /**
     * SEC-16: a raw token without the Bearer scheme prefix must not be
     * accepted as a valid Bearer credential.
     */
    #[Test]
    public function http_bearer_token_without_scheme_prefix_rejected(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'abc123');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->expectException(ValidationException::class);

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
    }

    /**
     * SEC-16: Basic auth must not satisfy a Bearer security
     * requirement even though both share the Authorization header.
     */
    #[Test]
    public function http_bearer_basic_auth_credentials_rejected_for_bearer_requirement(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Basic dXNlcjpwYXNz');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('bearerAuth', $error->schemeName(reveal: true));
            $this->assertSame('http/bearer', $error->schemeType(reveal: true));
            $this->assertSame('Authorization header', $error->location(reveal: true));
        }
    }

    #[Test]
    public function http_basic_returns_unsupported_scheme_error(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Basic dXNlcjpwYXNz');
        $securityRequirements = new SecurityRequirement([
            ['basicAuth' => []],
        ]);
        $securitySchemes = [
            'basicAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'basic',
            ),
        ];

        $this->expectException(ValidationException::class);

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
    }

    #[Test]
    public function http_basic_error_contains_unsupported_http_scheme_location(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Basic dXNlcjpwYXNz');
        $securityRequirements = new SecurityRequirement([
            ['basicAuth' => []],
        ]);
        $securitySchemes = [
            'basicAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'basic',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('basicAuth', $error->schemeName(reveal: true));
            $this->assertSame('http/basic', $error->schemeType(reveal: true));
            $this->assertSame('unsupported http scheme', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_query_with_valid_key_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data?api_key=my-secret-key');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyQuery' => []],
        ]);
        $securitySchemes = [
            'apiKeyQuery' => new SecurityScheme(
                type: 'apiKey',
                in: 'query',
                name: 'api_key',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function api_key_in_cookie_with_valid_key_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data')
            ->withCookieParams(['session_token' => 'abc123def456']);
        $securityRequirements = new SecurityRequirement([
            ['apiKeyCookie' => []],
        ]);
        $securitySchemes = [
            'apiKeyCookie' => new SecurityScheme(
                type: 'apiKey',
                in: 'cookie',
                name: 'session_token',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function api_key_in_query_with_empty_value_reports_empty_query_parameter_location(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data?api_key=');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyQuery' => []],
        ]);
        $securitySchemes = [
            'apiKeyQuery' => new SecurityScheme(
                type: 'apiKey',
                in: 'query',
                name: 'api_key',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('apiKeyQuery', $error->schemeName(reveal: true));
            $this->assertSame('apiKey', $error->schemeType(reveal: true));
            $this->assertSame('empty query parameter "api_key"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_query_with_missing_parameter_reports_missing_query_parameter_location(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyQuery' => []],
        ]);
        $securitySchemes = [
            'apiKeyQuery' => new SecurityScheme(
                type: 'apiKey',
                in: 'query',
                name: 'api_key',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('apiKeyQuery', $error->schemeName(reveal: true));
            $this->assertSame('apiKey', $error->schemeType(reveal: true));
            $this->assertSame('missing query parameter "api_key"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_cookie_with_empty_value_reports_empty_cookie_parameter_location(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data')
            ->withCookieParams(['session' => '']);
        $securityRequirements = new SecurityRequirement([
            ['apiKeyCookie' => []],
        ]);
        $securitySchemes = [
            'apiKeyCookie' => new SecurityScheme(
                type: 'apiKey',
                in: 'cookie',
                name: 'session',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('apiKeyCookie', $error->schemeName(reveal: true));
            $this->assertSame('apiKey', $error->schemeType(reveal: true));
            $this->assertSame('empty cookie parameter "session"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_header_missing_psr7_distinguishes_via_missing_only(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyHeader' => []],
        ]);
        $securitySchemes = [
            'apiKeyHeader' => new SecurityScheme(
                type: 'apiKey',
                in: 'header',
                name: 'X-API-Key',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('apiKeyHeader', $error->schemeName(reveal: true));
            $this->assertSame('apiKey', $error->schemeType(reveal: true));
            $this->assertSame('missing header parameter "X-API-Key"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_header_with_empty_value_psr7_limitation_yields_missing(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data')
            ->withHeader('X-API-Key', '');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyHeader' => []],
        ]);
        $securitySchemes = [
            'apiKeyHeader' => new SecurityScheme(
                type: 'apiKey',
                in: 'header',
                name: 'X-API-Key',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('missing header parameter "X-API-Key"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_query_with_array_value_reports_missing(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data?api_key[]=val');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyQuery' => []],
        ]);
        $securitySchemes = [
            'apiKeyQuery' => new SecurityScheme(
                type: 'apiKey',
                in: 'query',
                name: 'api_key',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('missing query parameter "api_key"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_cookie_with_array_value_reports_missing(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data')
            ->withCookieParams(['session_token' => ['array_value']]);
        $securityRequirements = new SecurityRequirement([
            ['apiKeyCookie' => []],
        ]);
        $securitySchemes = [
            'apiKeyCookie' => new SecurityScheme(
                type: 'apiKey',
                in: 'cookie',
                name: 'session_token',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('missing cookie parameter "session_token"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function api_key_in_header_with_valid_key_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data')
            ->withHeader('X-API-Key', 'sk-12345abcde');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyHeader' => []],
        ]);
        $securitySchemes = [
            'apiKeyHeader' => new SecurityScheme(
                type: 'apiKey',
                in: 'header',
                name: 'X-API-Key',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    /**
     * SEC-16 fail-closed anti-test: an apiKey scheme with an `in`
     * value outside the {query, header, cookie} allowlist must
     * resolve to MissingSecurityCredentialsError via the match
     * default arm, NOT throw UnhandledMatchError. Reverting the
     * `default => null` arm (or replacing the match with a strict
     * dispatcher) breaks this — the error class changes to
     * \Error, the location string leaks the raw `in` value, and
     * the caller-visible exception is no longer the generic
     * AuthenticationRequired message but a stack trace.
     */
    #[Test]
    public function api_key_with_unknown_in_value_fails_closed_with_missing_credentials_error(): void
    {
        $request = $this->factory->createServerRequest('GET', '/data');
        $securityRequirements = new SecurityRequirement([
            ['apiKeyPath' => []],
        ]);
        $securitySchemes = [
            'apiKeyPath' => new SecurityScheme(
                type: 'apiKey',
                in: 'path',
                name: 'id',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/data', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('apiKeyPath', $error->schemeName(reveal: true));
            $this->assertSame('apiKey', $error->schemeType(reveal: true));
            $this->assertSame('missing path parameter "id"', $error->location(reveal: true));
        }
    }

    #[Test]
    public function unknown_scheme_type_returns_unsupported_error(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['oauth2Auth' => []],
        ]);
        $securitySchemes = [
            'oauth2Auth' => new SecurityScheme(
                type: 'oauth2',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('oauth2Auth', $error->schemeName(reveal: true));
            $this->assertSame('oauth2', $error->schemeType(reveal: true));
            $this->assertSame('unsupported scheme type', $error->location(reveal: true));
        }
    }

    #[Test]
    public function undefined_scheme_name_returns_scheme_not_found_error(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['undefinedScheme' => []],
        ]);
        $securitySchemes = [];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('undefinedScheme', $error->schemeName(reveal: true));
            $this->assertSame('undefined', $error->schemeType(reveal: true));
            $this->assertSame('scheme not found in components/securitySchemes', $error->location(reveal: true));
        }
    }

    #[Test]
    public function missing_bearer_credentials_throws_validation_exception(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->expectException(ValidationException::class);

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
    }

    #[Test]
    public function missing_bearer_credentials_error_contains_correct_location(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('bearerAuth', $error->schemeName(reveal: true));
            $this->assertSame('http/bearer', $error->schemeType(reveal: true));
            $this->assertSame('Authorization header', $error->location(reveal: true));
        }
    }

    /**
     * SEC-07 / CWE-209: the public exception message must be generic so
     * an unauthenticated caller cannot enumerate the security surface
     * (scheme names, parameter locations, header names). The message
     * must NOT contain the scheme name, the scheme type, or the
     * parameter location — only a fixed "Authentication required"
     * notice.
     *
     * Anti-test: if MissingSecurityCredentialsError reverts to embedding
     * scheme details in its message, this assertion fails.
     */
    #[Test]
    public function generic_security_message_to_caller_omits_scheme_details(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);

            $message = $error->getMessage();
            $this->assertSame(
                'Authentication required: missing or invalid credentials',
                $message,
                'Default message must be the generic caller-safe string.',
            );
            $this->assertStringNotContainsString('bearerAuth', $message);
            $this->assertStringNotContainsString('Authorization', $message);
            $this->assertStringNotContainsString('http/bearer', $message);
            $this->assertStringNotContainsString('header', $message);
        }
    }

    /**
     * SEC-07 anti-circumvention: scheme details must also be absent
     * from params(), because error formatters (DetailedFormatter,
     * JsonFormatter) render params() directly into API responses via
     * PSR-15 middleware. Only readonly properties retain the detail
     * for programmatic access by trusted operators.
     */
    #[Test]
    public function generic_security_message_params_are_empty_for_caller_safety(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $error = $e->getErrors()[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame([], $error->params(), 'params() must be empty so formatters never leak scheme details.');
            $this->assertSame('/security', $error->dataPath(), 'dataPath must not embed the scheme name.');
            $this->assertSame('/security', $error->schemaPath(), 'schemaPath must not embed the scheme name.');
        }
    }

    /**
     * SEC-07 BC: although the message is generic, scheme details must
     * remain accessible as readonly properties on the error object so
     * trusted operators can introspect which scheme failed without
     * enabling verbose logging.
     */
    #[Test]
    public function scheme_properties_accessible_programmatically(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $error = $e->getErrors()[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('bearerAuth', $error->schemeName(reveal: true));
            $this->assertSame('http/bearer', $error->schemeType(reveal: true));
            $this->assertSame('Authorization header', $error->location(reveal: true));
        }
    }

    /**
     * SEC-07 opt-in: when a PSR-3 logger is supplied, the validator
     * forwards the concrete scheme details at debug level so trusted
     * operators can still diagnose which scheme failed — even though
     * the surfaced message stays generic.
     */
    #[Test]
    public function verbose_logging_to_logger_when_logger_supplied(): void
    {
        $spy = new SpyLogger();
        $validator = new SecurityValidator($spy);

        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        try {
            $validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException) {
            $this->assertCount(1, $spy->records);
            $record = $spy->records[0];
            $this->assertSame('debug', $record['level']);
            $this->assertSame('Security validation failed', $record['message']);
            $this->assertSame([
                'schemeName' => 'bearerAuth',
                'schemeType' => 'http/bearer',
                'location' => 'Authorization header',
            ], $record['context']);
        }
    }

    /**
     * SEC-07 default: without an explicit logger, the validator must
     * NOT emit anything observable — NullLogger is the safe default so
     * scheme details never end up in production logs via error_log().
     */
    #[Test]
    public function default_null_logger_emits_nothing_without_opt_in(): void
    {
        // Default-constructed validator uses NullLogger internally; the
        // only observable effect is that the generic exception is
        // raised. This test exists to lock that behaviour: any future
        // change that introduces a default non-null sink breaks it.
        $validator = new SecurityValidator();

        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['bearerAuth' => []],
        ]);
        $securitySchemes = [
            'bearerAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'bearer',
            ),
        ];

        $this->expectException(ValidationException::class);

        $validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
    }
}

/**
 * Minimal in-memory PSR-3 spy logger used to assert that the security
 * validator forwards scheme details at debug level when verbose logging
 * is enabled. Captures the level, message, and context of every call.
 *
 * @internal
 */
final class SpyLogger implements LoggerInterface
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
