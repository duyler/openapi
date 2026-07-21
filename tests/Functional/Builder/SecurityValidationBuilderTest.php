<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SecurityValidationBuilderTest extends TestCase
{
    private const string BEARER_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Bearer Security Builder API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: listUsers
      security:
        - bearerAuth: []
      responses:
        '200':
          description: OK
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
YAML;

    private const string APIKEY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: ApiKey Security Builder API
  version: 1.0.0
paths:
  /data:
    get:
      operationId: getData
      security:
        - apiKey: []
      responses:
        '200':
          description: OK
components:
  securitySchemes:
    apiKey:
      type: apiKey
      in: header
      name: X-API-Key
YAML;

    private const string NO_SECURITY_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: No Security Builder API
  version: 1.0.0
paths:
  /public:
    get:
      operationId: getPublic
      responses:
        '200':
          description: OK
YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function enable_security_validation_without_credentials_throws_with_missing_credentials_error(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame('bearerAuth', $errors[0]->schemeName(reveal: true));
            $this->assertSame('http/bearer', $errors[0]->schemeType(reveal: true));
            $this->assertSame('Authorization header', $errors[0]->location(reveal: true));
        }
    }

    #[Test]
    public function enable_security_validation_with_bearer_token_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.payload.signature');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function without_security_validation_no_credentials_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function enable_security_validation_without_security_requirements_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::NO_SECURITY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/public');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/public', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function enable_security_validation_apikey_without_header_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::APIKEY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame('apiKey', $errors[0]->schemeName(reveal: true));
            $this->assertSame('apiKey', $errors[0]->schemeType(reveal: true));
            $this->assertSame('missing header parameter "X-API-Key"', $errors[0]->location(reveal: true));
        }
    }

    #[Test]
    public function enable_security_validation_apikey_with_header_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::APIKEY_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/data')
            ->withHeader('X-API-Key', 'secret-key-123');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/data', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function enable_security_validation_bearer_with_wrong_scheme_prefix_throws(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users')
            ->withHeader('Authorization', 'Basic dXNlcjpwYXNz');

        $caught = false;

        try {
            $validator->validateRequest($request);
        } catch (ValidationException $exception) {
            $caught = true;
            $errors = $exception->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame('Authorization header', $errors[0]->location(reveal: true));
        }

        $this->assertTrue($caught, 'ValidationException should be thrown for wrong scheme prefix');
    }

    /**
     * SEC-07 opt-in: withSecurityVerboseLogging() must propagate a PSR-3
     * logger through the builder DI chain so the security validator
     * forwards scheme details at debug level when validation fails. The
     * surfaced exception message stays generic regardless.
     */
    #[Test]
    public function with_security_verbose_logging_propagates_logger_through_builder(): void
    {
        $spy = new SecurityVerboseSpyLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::BEARER_SPEC)
            ->enableSecurityValidation()
            ->withSecurityVerboseLogging($spy)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);

            // SEC-07: surfaced message must remain generic despite verbose
            // logging being enabled. Opt-in logging does not weaken the
            // caller-safe contract.
            $this->assertSame(
                'Authentication required: missing or invalid credentials',
                $errors[0]->getMessage(),
            );

            // Verbose logger must have received the concrete scheme details.
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
}

/**
 * Minimal in-memory PSR-3 spy logger used to assert that
 * withSecurityVerboseLogging() wires the logger end-to-end.
 *
 * @internal
 */
final class SecurityVerboseSpyLogger implements LoggerInterface
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
