<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\UnsupportedSecuritySchemeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Security\SchemeCredentialReporter;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerRegistry;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function str_repeat;

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
    public function http_bearer_multi_challenge_with_basic_suffix_rejected(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer fake, Basic dXNlcjpwYXNz');
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
    public function http_bearer_multi_challenge_with_duplicate_bearer_rejected(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer abc, Bearer def');
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
    public function http_bearer_with_trailing_whitespace_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer valid_token ');
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
    public function http_bearer_with_internal_whitespace_in_token_rejected(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer fake extra');
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
    public function http_bearer_with_payload_suffix_after_token_rejected(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer fake <script>alert(1)</script>');
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
    public function http_bearer_whitespace_padded_token_converted_to_missing_credentials_error(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer tok' . str_repeat(' ', 20000) . '!');
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
    public function http_basic_with_valid_credentials_passes(): void
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

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function http_digest_with_valid_credentials_passes(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Digest username="Mufasa", realm="testrealm@host.com", nonce="abc123", qop=auth');
        $securityRequirements = new SecurityRequirement([
            ['digestAuth' => []],
        ]);
        $securitySchemes = [
            'digestAuth' => new SecurityScheme(
                type: 'http',
                scheme: 'digest',
            ),
        ];

        $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function http_basic_without_header_reports_missing_credentials_error(): void
    {
        $request = $this->factory->createServerRequest('GET', '/users');
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
            $this->assertSame('Authorization header', $error->location(reveal: true));
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
            ['unknownAuth' => []],
        ]);
        $securitySchemes = [
            'unknownAuth' => new SecurityScheme(
                type: 'unknownSchemeType',
            ),
        ];

        try {
            $this->validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));
            $this->fail('Expected UnsupportedSecuritySchemeException was not thrown');
        } catch (UnsupportedSecuritySchemeException $e) {
            $this->assertSame('unknownAuth', $e->schemeName);
            $this->assertSame('unknownSchemeType', $e->schemeType);
            $this->assertNull($e->httpScheme);
            $this->assertStringContainsString('unknownSchemeType', $e->getMessage());
            $this->assertStringContainsString('unknownAuth', $e->getMessage());
            $this->assertStringContainsString('no built-in handler', $e->getMessage());
            $this->assertStringContainsString('withSecuritySchemeHandler()', $e->getMessage());
            $this->assertSame($e->getMessage(), (string) $e);
        }
    }

    #[Test]
    public function custom_handler_registry_replaces_builtin_dispatch(): void
    {
        $handler = new UnknownSchemeTypeHandler();
        $validator = new SecurityValidator(handlerRegistry: new SecuritySchemeHandlerRegistry([$handler]));
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['unknownAuth' => []],
        ]);
        $securitySchemes = [
            'unknownAuth' => new SecurityScheme(
                type: 'unknownSchemeType',
            ),
        ];

        $validator->validate(new SecurityValidationContext(request: $request, path: '/users', method: 'GET', securityRequirements: $securityRequirements, securitySchemes: $securitySchemes));

        $this->assertSame(['unknownAuth'], $handler->validatedSchemeNames);
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

    #[Test]
    public function default_null_logger_emits_nothing_without_opt_in(): void
    {
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

final class UnknownSchemeTypeHandler implements SecuritySchemeHandlerInterface
{
    /**
     * @var list<string>
     */
    public array $validatedSchemeNames = [];

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'unknownSchemeType' === $scheme->type;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $this->validatedSchemeNames[] = $reporter->schemeName;

        return null;
    }
}
