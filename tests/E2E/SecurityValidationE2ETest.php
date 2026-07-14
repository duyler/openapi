<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

final class SecurityValidationE2ETest extends TestCase
{
    private const string BEARER_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Bearer Auth API
  version: 1.0.0
security:
  - bearerAuth: []
paths:
  /users:
    get:
      summary: List users
      responses:
        '200':
          description: User list
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
YAML;

    private const string API_KEY_HEADER_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: API Key Header API
  version: 1.0.0
security:
  - apiKeyHeader: []
paths:
  /data:
    get:
      summary: Get data
      responses:
        '200':
          description: Data
components:
  securitySchemes:
    apiKeyHeader:
      type: apiKey
      in: header
      name: X-API-Key
YAML;

    private const string API_KEY_QUERY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: API Key Query API
  version: 1.0.0
security:
  - apiKeyQuery: []
paths:
  /data:
    get:
      summary: Get data
      responses:
        '200':
          description: Data
components:
  securitySchemes:
    apiKeyQuery:
      type: apiKey
      in: query
      name: api_key
YAML;

    private const string API_KEY_COOKIE_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: API Key Cookie API
  version: 1.0.0
security:
  - apiKeyCookie: []
paths:
  /data:
    get:
      summary: Get data
      responses:
        '200':
          description: Data
components:
  securitySchemes:
    apiKeyCookie:
      type: apiKey
      in: cookie
      name: session_token
YAML;

    private const string OPERATION_LEVEL_SECURITY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Operation Level Security API
  version: 1.0.0
paths:
  /public:
    get:
      summary: Public endpoint
      responses:
        '200':
          description: Public data
  /private:
    get:
      summary: Private endpoint
      security:
        - bearerAuth: []
      responses:
        '200':
          description: Private data
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
YAML;

    private const string NO_SECURITY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: No Security API
  version: 1.0.0
paths:
  /public:
    get:
      summary: Public endpoint
      responses:
        '200':
          description: Public data
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function bearer_without_token_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function bearer_without_token_contains_missing_security_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame('bearerAuth', $errors[0]->params()['schemeName']);
        }
    }

    #[Test]
    public function bearer_with_valid_token_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.test');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/users', $operation->path);
    }

    #[Test]
    public function bearer_with_empty_authorization_header_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', '');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function bearer_with_wrong_scheme_prefix_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Basic dXNlcjpwYXNz');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function api_key_header_missing_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_HEADER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function api_key_header_present_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_HEADER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data')
            ->withHeader('X-API-Key', 'sk-12345abcde');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function api_key_query_missing_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_QUERY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function api_key_query_present_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_QUERY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data?api_key=my-secret-key');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function api_key_cookie_missing_throws_validation_exception(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_COOKIE_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function api_key_cookie_present_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_COOKIE_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data')
            ->withCookieParams(['session_token' => 'abc123def456']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/data', $operation->path);
    }

    #[Test]
    public function security_disabled_by_default_allows_request_without_token(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/users', $operation->path);
    }

    #[Test]
    public function spec_without_security_allows_request_when_validation_enabled(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NO_SECURITY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/public');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/public', $operation->path);
    }

    #[Test]
    public function operation_level_security_public_endpoint_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPERATION_LEVEL_SECURITY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/public');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/public', $operation->path);
    }

    #[Test]
    public function operation_level_security_private_without_token_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPERATION_LEVEL_SECURITY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/private');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function operation_level_security_private_with_token_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OPERATION_LEVEL_SECURITY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/private')
            ->withHeader('Authorization', 'Bearer my-token');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/private', $operation->path);
    }

    #[Test]
    public function api_key_query_with_empty_value_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_QUERY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data?api_key=');

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function api_key_cookie_with_empty_value_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::API_KEY_COOKIE_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data')
            ->withCookieParams(['session_token' => '']);

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }
}
