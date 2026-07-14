<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MissingParameterException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function basename;
use function implode;
use function json_encode;
use function str_contains;

use const JSON_THROW_ON_ERROR;

final class OpenApiVersionCompatibilityTest extends TestCase
{
    private const string ORIGINAL_DIR = __DIR__ . '/../fixtures';
    private const string MIRROR_DIR = __DIR__ . '/Fixtures/version-compat';

    private const string VERSION_310 = '3.1.0';
    private const string VERSION_320 = '3.2.0';

    /**
     * @var array<string, string>
     */
    private const array SPECS = [
        'petstore' => '/petstore.yaml',
        'discriminator' => '/advanced-specs/discriminator.yaml',
        'type-coercion' => '/advanced-specs/type-coercion.yaml',
        'format-validation' => '/advanced-specs/format-validation.yaml',
        'xml-endpoint' => '/security-specs/xml-endpoint.yaml',
        'webhook-openapi' => '/webhook-openapi.yaml',
        'complex-schemas' => '/request-validation-specs/complex-schemas.yaml',
        'response-headers' => '/response-validation-specs/headers.yaml',
    ];

    /**
     * @var array<string, array{string, string}>
     */
    private const array CUSTOM_SPECS = [
        'streaming' => ['streaming-3.1.0.yaml', 'streaming-3.2.0.yaml'],
        'security-scheme' => ['security-scheme-3.1.0.yaml', 'security-scheme-3.2.0.yaml'],
        'deprecation' => ['deprecation-3.1.0.yaml', 'deprecation-3.2.0.yaml'],
    ];

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allSpecsBothVersionsProvider(): array
    {
        $cases = [];

        foreach (self::SPECS as $name => $relativePath) {
            $cases[$name . ' (' . self::VERSION_310 . ')'] = [self::ORIGINAL_DIR . $relativePath];
            $cases[$name . ' (' . self::VERSION_320 . ')'] = [self::MIRROR_DIR . '/' . basename($relativePath)];
        }

        foreach (self::CUSTOM_SPECS as $name => $paths) {
            $cases[$name . ' (' . self::VERSION_310 . ')'] = [self::MIRROR_DIR . '/' . $paths[0]];
            $cases[$name . ' (' . self::VERSION_320 . ')'] = [self::MIRROR_DIR . '/' . $paths[1]];
        }

        return $cases;
    }

    public static function petstoreSpecProvider(): array
    {
        return self::versionPair(self::SPECS['petstore']);
    }

    public static function discriminatorSpecProvider(): array
    {
        return self::versionPair(self::SPECS['discriminator']);
    }

    public static function typeCoercionSpecProvider(): array
    {
        return self::versionPair(self::SPECS['type-coercion']);
    }

    public static function formatValidationSpecProvider(): array
    {
        return self::versionPair(self::SPECS['format-validation']);
    }

    public static function xmlEndpointSpecProvider(): array
    {
        return self::versionPair(self::SPECS['xml-endpoint']);
    }

    public static function webhookSpecProvider(): array
    {
        return self::versionPair(self::SPECS['webhook-openapi']);
    }

    public static function complexSchemasSpecProvider(): array
    {
        return self::versionPair(self::SPECS['complex-schemas']);
    }

    public static function responseHeadersSpecProvider(): array
    {
        return self::versionPair(self::SPECS['response-headers']);
    }

    public static function streamingSpecProvider(): array
    {
        return self::customVersionPair('streaming');
    }

    public static function securitySchemeSpecProvider(): array
    {
        return self::customVersionPair('security-scheme');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function deprecationSpecProvider(): array
    {
        [$path310, $path320] = self::CUSTOM_SPECS['deprecation'];

        return [
            self::VERSION_310 => [self::MIRROR_DIR . '/' . $path310, false],
            self::VERSION_320 => [self::MIRROR_DIR . '/' . $path320, true],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deprecationSpecOnlyProvider(): array
    {
        [$path310, $path320] = self::CUSTOM_SPECS['deprecation'];

        return [
            self::VERSION_310 => [self::MIRROR_DIR . '/' . $path310],
            self::VERSION_320 => [self::MIRROR_DIR . '/' . $path320],
        ];
    }

    #[Test]
    #[DataProvider('allSpecsBothVersionsProvider')]
    public function spec_parses_without_errors_for_both_versions(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        self::assertInstanceOf(OpenApiValidator::class, $validator);

        $document = $validator->getDocument();

        self::assertContains($document->openapi, [self::VERSION_310, self::VERSION_320]);
        self::assertNotEmpty($document->info->title);
        self::assertNotEmpty($document->info->version);
    }

    #[Test]
    public function unsupported_openapi_version_is_rejected(): void
    {
        $yaml = "openapi: 4.0.0\ninfo:\n  title: Bad\n  version: '1.0.0'\npaths: {}\n";

        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Unsupported OpenAPI version');
        OpenApiValidatorBuilder::create()->fromYamlString($yaml)->build();
    }

    #[Test]
    #[DataProvider('petstoreSpecProvider')]
    public function petstore_create_pet_validates_request(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/pets', [
            'name' => 'Rex',
            'tag' => 'dog',
        ]);

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/pets', $operation->path);
    }

    #[Test]
    #[DataProvider('petstoreSpecProvider')]
    public function petstore_rejects_request_with_missing_required_name(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/pets', ['tag' => 'dog']);

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('discriminatorSpecProvider')]
    public function discriminator_validates_cat_payload(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/pet/simple', [
            'petType' => 'cat',
            'meow' => true,
        ]);

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/pet/simple', $operation->path);
    }

    #[Test]
    #[DataProvider('discriminatorSpecProvider')]
    public function discriminator_rejects_payload_missing_required_property(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/pet/simple', ['petType' => 'cat']);

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('typeCoercionSpecProvider')]
    public function type_coercion_validates_query_parameters(string $specPath): void
    {
        $validator = $this->buildCoercionValidator($specPath);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/request/coercion?age=25&price=9.99&active=true&name=Widget',
        );

        $operation = $validator->validateRequest($request);

        self::assertSame('GET', $operation->method);
        self::assertSame('/request/coercion', $operation->path);
    }

    #[Test]
    #[DataProvider('typeCoercionSpecProvider')]
    public function type_coercion_rejects_value_below_minimum(string $specPath): void
    {
        $validator = $this->buildCoercionValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/request/coercion-validation?age=5');

        $this->expectException(MinimumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('formatValidationSpecProvider')]
    public function format_validation_accepts_valid_email(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/formats/query?email=user@example.com',
        );

        $operation = $validator->validateRequest($request);

        self::assertSame('/formats/query', $operation->path);
    }

    #[Test]
    #[DataProvider('formatValidationSpecProvider')]
    public function format_validation_rejects_invalid_email(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/formats/query?email=not-an-email',
        );

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('xmlEndpointSpecProvider')]
    public function xml_endpoint_accepts_valid_xml_body(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildXmlRequest('POST', '/xml-endpoint', '<root><name>Test</name><value>42</value></root>');

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/xml-endpoint', $operation->path);
    }

    #[Test]
    #[DataProvider('xmlEndpointSpecProvider')]
    public function xml_endpoint_rejects_body_missing_required_field(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildXmlRequest('POST', '/xml-endpoint', '<root><value>42</value></root>');

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('webhookSpecProvider')]
    public function webhook_validates_payment_updated_event(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/webhook', [
            'payment_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'completed',
            'amount' => 99.99,
        ]);

        $operation = $validator->validateWebhook($request, 'payment.updated');

        self::assertSame('POST', $operation->method);
    }

    #[Test]
    #[DataProvider('webhookSpecProvider')]
    public function webhook_rejects_invalid_status_enum(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/webhook', [
            'payment_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'unknown',
            'amount' => 99.99,
        ]);

        $caught = null;

        try {
            $validator->validateWebhook($request, 'payment.updated');
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        }

        self::assertInstanceOf(EnumError::class, $caught);
    }

    #[Test]
    #[DataProvider('complexSchemasSpecProvider')]
    public function complex_schemas_validates_nested_path_params(string $specPath): void
    {
        $validator = $this->buildCoercionValidator($specPath);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/articles/550e8400-e29b-41d4-a716-446655440000/comments/42',
        );

        $operation = $validator->validateRequest($request);

        self::assertSame('GET', $operation->method);
        self::assertSame('/articles/{articleId}/comments/{commentId}', $operation->path);
    }

    #[Test]
    #[DataProvider('complexSchemasSpecProvider')]
    public function complex_schemas_rejects_comment_id_below_minimum(string $specPath): void
    {
        $validator = $this->buildCoercionValidator($specPath);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/articles/550e8400-e29b-41d4-a716-446655440000/comments/0',
        );

        $this->expectException(MinimumError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('responseHeadersSpecProvider')]
    public function response_headers_validate_when_required_header_present(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/headers/optional-required');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Required-Header', 'value-123')
            ->withBody($this->psrFactory->createStream(
                (string) json_encode(['id' => 'abc'], JSON_THROW_ON_ERROR),
            ));

        $validator->validateResponse($response, $operation);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('responseHeadersSpecProvider')]
    public function response_headers_reject_when_required_header_missing(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/headers/optional-required');
        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                (string) json_encode(['id' => 'abc'], JSON_THROW_ON_ERROR),
            ));

        $this->expectException(MissingParameterException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    #[DataProvider('streamingSpecProvider')]
    public function streaming_response_validates_valid_ndjson_items(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = implode("\n", [
            (string) json_encode([
                'timestamp' => '2026-01-15T10:30:00Z',
                'level' => 'info',
                'message' => 'first',
            ], JSON_THROW_ON_ERROR),
            (string) json_encode([
                'timestamp' => '2026-01-15T10:31:00Z',
                'level' => 'error',
                'message' => 'second',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $validator->validateResponse($response, $operation);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('streamingSpecProvider')]
    public function streaming_response_rejects_invalid_item_in_stream(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/logs');
        $operation = $validator->validateRequest($request);

        $body = implode("\n", [
            (string) json_encode([
                'timestamp' => '2026-01-15T10:30:00Z',
                'level' => 'info',
                'message' => 'ok',
            ], JSON_THROW_ON_ERROR),
            (string) json_encode([
                'timestamp' => '2026-01-15T10:31:00Z',
                'level' => 'unknown-level',
                'message' => 'bad',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $this->psrFactory->createResponse(200)
            ->withHeader('Content-Type', 'application/jsonl')
            ->withBody($this->psrFactory->createStream($body));

        $caught = null;

        try {
            $validator->validateResponse($response, $operation);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        }

        self::assertInstanceOf(EnumError::class, $caught);
    }

    #[Test]
    #[DataProvider('securitySchemeSpecProvider')]
    public function security_scheme_accepts_request_with_valid_bearer_token(string $specPath): void
    {
        $validator = $this->buildSecurityValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/protected')
            ->withHeader('Authorization', 'Bearer eyJhbGciOiJIUzI1NiJ9.payload.signature');

        $operation = $validator->validateRequest($request);

        self::assertSame('GET', $operation->method);
        self::assertSame('/protected', $operation->path);
    }

    #[Test]
    #[DataProvider('securitySchemeSpecProvider')]
    public function security_scheme_rejects_request_with_missing_credentials(string $specPath): void
    {
        $validator = $this->buildSecurityValidator($specPath);

        $request = $this->psrFactory->createServerRequest('GET', '/protected');

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    #[DataProvider('deprecationSpecProvider')]
    public function deprecation_warning_emitted_only_for_openapi_3_2_0(string $specPath, bool $expectWarning): void
    {
        $records = [];
        $logger = $this->buildCapturingLogger($records);

        $this->buildValidatorWithLogger($specPath, $logger);

        if ($expectWarning) {
            self::assertGreaterThanOrEqual(1, $records['warning'] ?? 0);
            self::assertTrue(
                $this->recordsContain($records['messages'] ?? [], 'nullable'),
                'Expected deprecation warning mentioning "nullable" for openapi 3.2.0 spec.',
            );
        } else {
            self::assertSame(0, $records['warning'] ?? 0);
        }
    }

    #[Test]
    #[DataProvider('deprecationSpecOnlyProvider')]
    public function deprecation_spec_still_validates_request_for_both_versions(string $specPath): void
    {
        $validator = $this->buildValidator($specPath);

        $request = $this->buildJsonRequest('POST', '/resource', ['name' => 'widget']);

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/resource', $operation->path);
    }

    /**
     * @return array<string, array{string}>
     */
    private static function versionPair(string $relativePath): array
    {
        return [
            self::VERSION_310 => [self::ORIGINAL_DIR . $relativePath],
            self::VERSION_320 => [self::MIRROR_DIR . '/' . basename($relativePath)],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    private static function customVersionPair(string $name): array
    {
        [$path310, $path320] = self::CUSTOM_SPECS[$name];

        return [
            self::VERSION_310 => [self::MIRROR_DIR . '/' . $path310],
            self::VERSION_320 => [self::MIRROR_DIR . '/' . $path320],
        ];
    }

    private function buildValidator(string $specPath): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile($specPath)
            ->build();
    }

    private function buildValidatorWithLogger(string $specPath, LoggerInterface $logger): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile($specPath)
            ->withLogger($logger)
            ->build();
    }

    private function buildCoercionValidator(string $specPath): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile($specPath)
            ->enableCoercion()
            ->build();
    }

    private function buildSecurityValidator(string $specPath): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile($specPath)
            ->enableSecurityValidation()
            ->build();
    }

    private function buildJsonRequest(string $method, string $path, array $body): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest($method, $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(
                (string) json_encode($body, JSON_THROW_ON_ERROR),
            ));
    }

    private function buildXmlRequest(string $method, string $path, string $xmlBody): ServerRequestInterface
    {
        $xml = '<?xml version="1.0"?>' . $xmlBody;

        return $this->psrFactory->createServerRequest($method, $path)
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xml));
    }

    /**
     * @param array<string, mixed> $records
     */
    private function buildCapturingLogger(array &$records): LoggerInterface
    {
        $records = ['warning' => 0, 'messages' => []];

        return new class ($records) implements LoggerInterface {
            /** @param array<string, mixed> $records */
            public function __construct(private array &$records) {}

            public function emergency($message, array $context = []): void
            {
                $this->record('emergency', (string) $message);
            }

            public function alert($message, array $context = []): void
            {
                $this->record('alert', (string) $message);
            }

            public function critical($message, array $context = []): void
            {
                $this->record('critical', (string) $message);
            }

            public function error($message, array $context = []): void
            {
                $this->record('error', (string) $message);
            }

            public function warning($message, array $context = []): void
            {
                $this->record('warning', (string) $message);
            }

            public function notice($message, array $context = []): void
            {
                $this->record('notice', (string) $message);
            }

            public function info($message, array $context = []): void
            {
                $this->record('info', (string) $message);
            }

            public function debug($message, array $context = []): void
            {
                $this->record('debug', (string) $message);
            }

            public function log($level, $message, array $context = []): void
            {
                $this->record((string) $level, (string) $message);
            }

            private function record(string $level, string $message): void
            {
                if (false === isset($this->records[$level])) {
                    $this->records[$level] = 0;
                }

                ++$this->records[$level];

                if (false === isset($this->records['messages'])) {
                    $this->records['messages'] = [];
                }

                /** @var list<string> $messages */
                $messages = $this->records['messages'];
                $messages[] = $message;
                $this->records['messages'] = $messages;
            }
        };
    }

    /**
     * @param list<string> $messages
     */
    private function recordsContain(array $messages, string $needle): bool
    {
        return array_any($messages, fn($message) => str_contains((string) $message, $needle));
    }
}
