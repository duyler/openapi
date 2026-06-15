<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

use function count;
use function json_encode;
use function str_contains;

final class BuilderConfigurationTest extends TestCase
{
    private const string DEPRECATED_PROPERTY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Deprecated Property Reporting API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - newName
              properties:
                newName:
                  type: string
                legacyCode:
                  type: string
                  deprecated: true
      responses:
        '201':
          description: Created
YAML;

    private const string OBJECT_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Empty Array Object Schema API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '201':
          description: Created
YAML;

    private const string ARRAY_SCHEMA_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Empty Array Array Schema API
  version: 1.0.0
paths:
  /data:
    post:
      operationId: createData
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: array
      responses:
        '201':
          description: Created
YAML;

    private const string KITCHEN_SINK_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Kitchen Sink API
  version: 1.0.0
servers:
  - url: https://api.example.com/v1
paths:
  /items:
    post:
      operationId: createItem
      security:
        - bearerAuth: []
      parameters:
        - name: priority
          in: query
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 10
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
              properties:
                name:
                  type: string
                  format: email
                legacyCode:
                  type: string
                  deprecated: true
      responses:
        '201':
          description: Created
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function enable_report_deprecated_logs_warning_when_deprecated_property_used(): void
    {
        $logger = new CapturingLogger();
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DEPRECATED_PROPERTY_SPEC)
            ->enableReportDeprecated()
            ->withLogger($logger)
            ->build();
        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream((string) json_encode([
                'newName' => 'fresh',
                'legacyCode' => 'legacy',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('POST', $operation->method);
        $this->assertSame('/users', $operation->path);
        $warnings = $logger->warningMessages();
        $this->assertSame(1, count($warnings));
        $this->assertTrue(str_contains($warnings[0], 'Deprecated property'));
        $this->assertTrue(str_contains($warnings[0], 'legacyCode'));
    }

    #[Test]
    public function enable_report_deprecated_does_not_log_when_deprecated_property_absent(): void
    {
        $logger = new CapturingLogger();
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DEPRECATED_PROPERTY_SPEC)
            ->enableReportDeprecated()
            ->withLogger($logger)
            ->build();
        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream((string) json_encode([
                'newName' => 'fresh',
            ])));

        $validator->validateRequest($request);

        $this->assertSame([], $logger->warningMessages());
    }

    #[Test]
    public function prefer_object_strategy_accepts_empty_array_against_object_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OBJECT_SCHEMA_SPEC)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();
        $request = $this->buildJsonRequest('/data', '[]');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/data', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function prefer_array_strategy_accepts_empty_array_against_array_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ARRAY_SCHEMA_SPEC)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
            ->build();
        $request = $this->buildJsonRequest('/data', '[]');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/data', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function prefer_array_strategy_rejects_empty_array_against_object_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OBJECT_SCHEMA_SPEC)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
            ->build();
        $request = $this->buildJsonRequest('/data', '[]');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError because PreferArray treats [] as array, not object');
        } catch (TypeMismatchError $error) {
            $this->assertSame('object', $error->params()['expected']);
            $this->assertSame('array', $error->params()['actual']);
            $this->assertSame('type', $error->keyword());
        }
    }

    #[Test]
    public function prefer_object_strategy_rejects_empty_array_against_array_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::ARRAY_SCHEMA_SPEC)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
            ->build();
        $request = $this->buildJsonRequest('/data', '[]');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError because PreferObject treats [] as object, not array');
        } catch (TypeMismatchError $error) {
            $this->assertSame('array', $error->params()['expected']);
            $this->assertSame('array', $error->params()['actual']);
            $this->assertSame('type', $error->keyword());
        }
    }

    #[Test]
    public function reject_strategy_rejects_empty_array_against_object_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OBJECT_SCHEMA_SPEC)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::Reject)
            ->build();
        $request = $this->buildJsonRequest('/data', '[]');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError because Reject strategy forbids empty arrays');
        } catch (TypeMismatchError $error) {
            $this->assertSame('object', $error->params()['expected']);
            $this->assertSame('array', $error->params()['actual']);
            $this->assertSame('type', $error->keyword());
        }
    }

    #[Test]
    public function allow_both_strategy_accepts_empty_array_against_object_schema(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::OBJECT_SCHEMA_SPEC)
            ->withEmptyArrayStrategy(EmptyArrayStrategy::AllowBoth)
            ->build();
        $request = $this->buildJsonRequest('/data', '[]');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/data', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function kitchen_sink_all_builder_methods_chained_works_e2e(): void
    {
        $logger = new CapturingLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::KITCHEN_SINK_SPEC)
            ->enableCoercion()
            ->enableSecurityValidation()
            ->enableStrictFormats()
            ->enableReportDeprecated()
            ->enableServerPathResolution()
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
            ->withErrorFormatter(new DetailedFormatter())
            ->withLogger($logger)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/v1/items?priority=5')
            ->withHeader('Authorization', 'Bearer test-token')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream((string) json_encode([
                'name' => 'user@example.com',
                'legacyCode' => 'legacy',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('POST', $operation->method);
        $warnings = $logger->warningMessages();
        $deprecatedWarnings = $this->filterDeprecated($warnings, 'legacyCode');
        $this->assertSame(1, count($deprecatedWarnings));
    }

    #[Test]
    public function kitchen_sink_rejects_request_without_required_security_token(): void
    {
        $logger = new CapturingLogger();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::KITCHEN_SINK_SPEC)
            ->enableCoercion()
            ->enableSecurityValidation()
            ->enableStrictFormats()
            ->enableReportDeprecated()
            ->enableServerPathResolution()
            ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
            ->withErrorFormatter(new DetailedFormatter())
            ->withLogger($logger)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/v1/items?priority=5')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream((string) json_encode([
                'name' => 'user@example.com',
            ])));

        try {
            $validator->validateRequest($request);
            $this->fail('Expected ValidationException because bearer token is missing');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();
            $this->assertNotEmpty($errors);
            $this->assertSame('security', $errors[0]->keyword());
        }
    }

    private function buildJsonRequest(string $path, string $body): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream($body));
    }

    /**
     * @param list<string> $warnings
     * @return list<string>
     */
    private function filterDeprecated(array $warnings, string $propertyName): array
    {
        $filtered = [];

        foreach ($warnings as $message) {
            if (str_contains($message, 'Deprecated property') && str_contains($message, $propertyName)) {
                $filtered[] = $message;
            }
        }

        return $filtered;
    }
}

final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    private array $records = [];

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<string>
     */
    public function warningMessages(): array
    {
        $messages = [];

        foreach ($this->records as $record) {
            if ($record['level'] === LogLevel::WARNING) {
                $messages[] = $record['message'];
            }
        }

        return $messages;
    }
}
