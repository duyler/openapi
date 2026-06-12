<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
        // Arrange
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

        // Act
        $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);

        // Assert — no exception means success
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function http_basic_returns_unsupported_scheme_error(): void
    {
        // Arrange
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

        // Assert
        $this->expectException(ValidationException::class);

        // Act
        $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);
    }

    #[Test]
    public function http_basic_error_contains_unsupported_http_scheme_location(): void
    {
        // Arrange
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

        // Act
        try {
            $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            // Assert
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('basicAuth', $error->params()['schemeName']);
            $this->assertSame('http/basic', $error->params()['schemeType']);
            $this->assertSame('unsupported http scheme', $error->params()['location']);
        }
    }

    #[Test]
    public function api_key_in_query_with_valid_key_passes(): void
    {
        // Arrange
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

        // Act
        $this->validator->validate($request, '/data', 'GET', $securityRequirements, $securitySchemes);

        // Assert — no exception means success
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function api_key_in_cookie_with_valid_key_passes(): void
    {
        // Arrange
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

        // Act
        $this->validator->validate($request, '/data', 'GET', $securityRequirements, $securitySchemes);

        // Assert — no exception means success
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function api_key_in_header_with_valid_key_passes(): void
    {
        // Arrange
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

        // Act
        $this->validator->validate($request, '/data', 'GET', $securityRequirements, $securitySchemes);

        // Assert — no exception means success
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unknown_scheme_type_returns_unsupported_error(): void
    {
        // Arrange
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['oauth2Auth' => []],
        ]);
        $securitySchemes = [
            'oauth2Auth' => new SecurityScheme(
                type: 'oauth2',
            ),
        ];

        // Act
        try {
            $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            // Assert
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('oauth2Auth', $error->params()['schemeName']);
            $this->assertSame('oauth2', $error->params()['schemeType']);
            $this->assertSame('unsupported scheme type', $error->params()['location']);
        }
    }

    #[Test]
    public function undefined_scheme_name_returns_scheme_not_found_error(): void
    {
        // Arrange
        $request = $this->factory->createServerRequest('GET', '/users');
        $securityRequirements = new SecurityRequirement([
            ['undefinedScheme' => []],
        ]);
        $securitySchemes = [];

        // Act
        try {
            $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            // Assert
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('undefinedScheme', $error->params()['schemeName']);
            $this->assertSame('undefined', $error->params()['schemeType']);
            $this->assertSame('scheme not found in components/securitySchemes', $error->params()['location']);
        }
    }

    #[Test]
    public function missing_bearer_credentials_throws_validation_exception(): void
    {
        // Arrange
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

        // Assert
        $this->expectException(ValidationException::class);

        // Act
        $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);
    }

    #[Test]
    public function missing_bearer_credentials_error_contains_correct_location(): void
    {
        // Arrange
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

        // Act
        try {
            $this->validator->validate($request, '/users', 'GET', $securityRequirements, $securitySchemes);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            // Assert
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $error = $errors[0];
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
            $this->assertSame('bearerAuth', $error->params()['schemeName']);
            $this->assertSame('http/bearer', $error->params()['schemeType']);
            $this->assertSame('Authorization header', $error->params()['location']);
        }
    }
}
