<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Security\SchemeCredentialReporter;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function implode;
use function rtrim;
use function sprintf;

final class OpenApiValidatorBuilderWithSecuritySchemeHandlerTest extends TestCase
{
    private const string BASIC = 'basicAuth';
    private const string DIGEST = 'digestAuth';
    private const string BEARER = 'bearerAuth';
    private const string API_KEY = 'headerApiKey';
    private const string OTHER_API_KEY = 'otherApiKey';
    private const string MUTUAL_TLS = 'mutualTlsAuth';
    private const string OAUTH2 = 'oauth2Auth';
    private const string OPEN_ID_CONNECT = 'openIdConnectAuth';
    private const string UNKNOWN = 'unknownAuth';

    private const array SCHEME_YAML = [
        self::BASIC => "    basicAuth:\n      type: http\n      scheme: basic\n",
        self::DIGEST => "    digestAuth:\n      type: http\n      scheme: digest\n",
        self::BEARER => "    bearerAuth:\n      type: http\n      scheme: bearer\n",
        self::API_KEY => "    headerApiKey:\n      type: apiKey\n      in: header\n      name: X-API-Key\n",
        self::OTHER_API_KEY => "    otherApiKey:\n      type: apiKey\n      in: header\n      name: X-Other-Key\n",
        self::MUTUAL_TLS => "    mutualTlsAuth:\n      type: mutualTLS\n",
        self::OAUTH2 => "    oauth2Auth:\n      type: oauth2\n",
        self::OPEN_ID_CONNECT => "    openIdConnectAuth:\n      type: openIdConnect\n",
        self::UNKNOWN => "    unknownAuth:\n      type: unknownSchemeType\n",
    ];

    private const string WEBHOOK_HANDLER_YAML = <<<'YAML'
        openapi: 3.1.0
        info:
          title: Webhook Security Scheme Handler API
          version: 1.0.0
        security:
          - bearerAuth: []
        webhooks:
          payment.completed:
            post:
              operationId: onPaymentCompleted
              responses:
                '200':
                  description: OK
        components:
          securitySchemes:
            bearerAuth:
              type: http
              scheme: bearer
        YAML;

    private const string CALLBACK_HANDLER_YAML = <<<'YAML'
        openapi: 3.1.0
        info:
          title: Callback Security Scheme Handler API
          version: 1.0.0
        security:
          - bearerAuth: []
        paths:
          /subscriptions:
            post:
              operationId: createSubscription
              responses:
                '201':
                  description: Created
              callbacks:
                subscriptionEvent:
                  '/callbacks/event':
                    post:
                      operationId: onSubscriptionEvent
                      responses:
                        '200':
                          description: OK
        components:
          securitySchemes:
            bearerAuth:
              type: http
              scheme: bearer
        YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    public static function matrix_scheme_cases(): array
    {
        return [
            'http basic' => [
                self::BASIC,
                [['Authorization', 'Basic dXNlcjpwYXNz']],
            ],
            'http digest' => [
                self::DIGEST,
                [['Authorization', 'Digest username="Mufasa", realm="testrealm@host.com", nonce="abc123", qop=auth']],
            ],
            'http bearer' => [
                self::BEARER,
                [['Authorization', 'Bearer matrix-token']],
            ],
            'header api key' => [
                self::API_KEY,
                [['X-API-Key', 'primary-secret']],
            ],
            'mutual tls' => [
                self::MUTUAL_TLS,
                [['X-Client-Cert', 'client-certificate-contents']],
            ],
            'oauth2' => [
                self::OAUTH2,
                [['Authorization', 'Bearer oauth-token']],
            ],
            'open id connect' => [
                self::OPEN_ID_CONNECT,
                [['Authorization', 'Bearer oidc-token']],
            ],
        ];
    }

    #[Test]
    #[DataProvider('matrix_scheme_cases')]
    public function or_requirement_passes_with_credentials_for_first_alternative(string $schemeKey, array $credentials): void
    {
        $partnerKey = $this->partnerFor($schemeKey);
        $validator = $this->buildValidator([$schemeKey, $partnerKey], [[$schemeKey], [$partnerKey]]);

        $operation = $validator->validateRequest($this->applyCredentials($credentials));

        $this->assertSame('/secure', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    #[DataProvider('matrix_scheme_cases')]
    public function or_requirement_passes_with_credentials_for_second_alternative(string $schemeKey, array $credentials): void
    {
        $partnerKey = $this->partnerFor($schemeKey);
        $validator = $this->buildValidator([$schemeKey, $partnerKey], [[$schemeKey], [$partnerKey]]);

        $operation = $validator->validateRequest(
            $this->applyCredentials($this->credentialsFor($partnerKey)),
        );

        $this->assertSame('/secure', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    #[DataProvider('matrix_scheme_cases')]
    public function or_requirement_fails_without_any_credentials(string $schemeKey, array $credentials): void
    {
        unset($credentials);
        $partnerKey = $this->partnerFor($schemeKey);
        $validator = $this->buildValidator([$schemeKey, $partnerKey], [[$schemeKey], [$partnerKey]]);

        try {
            $validator->validateRequest($this->createRequest());
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            $this->assertCount(2, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[1]);
            $this->assertSame($schemeKey, $errors[0]->schemeName(reveal: true));
            $this->assertSame($partnerKey, $errors[1]->schemeName(reveal: true));
        }
    }

    #[Test]
    #[DataProvider('matrix_scheme_cases')]
    public function and_requirement_passes_with_all_credentials(string $schemeKey, array $credentials): void
    {
        $partnerKey = $this->partnerFor($schemeKey);
        $validator = $this->buildValidator([$schemeKey, $partnerKey], [[$schemeKey, $partnerKey]]);

        $operation = $validator->validateRequest(
            $this->applyCredentials([...$credentials, ...$this->credentialsFor($partnerKey)]),
        );

        $this->assertSame('/secure', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    #[DataProvider('matrix_scheme_cases')]
    public function and_requirement_fails_with_partial_credentials(string $schemeKey, array $credentials): void
    {
        $partnerKey = $this->partnerFor($schemeKey);
        $validator = $this->buildValidator([$schemeKey, $partnerKey], [[$schemeKey, $partnerKey]]);

        try {
            $validator->validateRequest($this->applyCredentials($credentials));
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame($partnerKey, $errors[0]->schemeName(reveal: true));
        }
    }

    #[Test]
    public function or_requirement_with_unsupported_second_alternative_passes_via_valid_branch(): void
    {
        $validator = $this->buildValidator([self::BEARER, self::UNKNOWN], [[self::BEARER], [self::UNKNOWN]]);

        $operation = $validator->validateRequest(
            $this->createRequest()->withHeader('Authorization', 'Bearer known-token'),
        );

        $this->assertSame('/secure', $operation->path);
    }

    #[Test]
    public function or_requirement_with_unsupported_first_alternative_passes_via_valid_branch(): void
    {
        $validator = $this->buildValidator([self::BEARER, self::UNKNOWN], [[self::UNKNOWN], [self::BEARER]]);

        $operation = $validator->validateRequest(
            $this->createRequest()->withHeader('Authorization', 'Bearer known-token'),
        );

        $this->assertSame('/secure', $operation->path);
    }

    #[Test]
    public function custom_introspection_handler_overrides_builtin_bearer_handler(): void
    {
        $handler = new IntrospectionStubHandler();
        $validator = $this->buildValidatorWithHandlers([self::BEARER], [[self::BEARER]], [$handler]);

        $operation = $validator->validateRequest(
            $this->createRequest()->withHeader('X-Introspected-Token', 'active-token'),
        );

        $this->assertSame('/secure', $operation->path);
        $this->assertSame(['bearerAuth'], $handler->validatedSchemeNames);
    }

    #[Test]
    public function custom_introspection_handler_missing_token_reports_stub_location(): void
    {
        $handler = new IntrospectionStubHandler();
        $validator = $this->buildValidatorWithHandlers([self::BEARER], [[self::BEARER]], [$handler]);

        try {
            $validator->validateRequest($this->createRequest());
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $exception) {
            $errors = $exception->getErrors();

            $this->assertCount(1, $errors);
            $this->assertInstanceOf(MissingSecurityCredentialsError::class, $errors[0]);
            $this->assertSame('bearerAuth', $errors[0]->schemeName(reveal: true));
            $this->assertSame('http/bearer', $errors[0]->schemeType(reveal: true));
            $this->assertSame('X-Introspected-Token header', $errors[0]->location(reveal: true));
        }

        $this->assertSame(['bearerAuth'], $handler->validatedSchemeNames);
    }

    #[Test]
    public function last_registered_competing_handler_wins_the_lookup(): void
    {
        $first = new HeaderRequirementHandler('X-First-Token');
        $second = new HeaderRequirementHandler('X-Second-Token');
        $validator = $this->buildValidatorWithHandlers([self::BEARER], [[self::BEARER]], [$first, $second]);

        $operation = $validator->validateRequest(
            $this->createRequest()->withHeader('X-Second-Token', 'anything'),
        );

        $this->assertSame('/secure', $operation->path);
        $this->assertSame([], $first->validatedSchemeNames);
        $this->assertSame(['bearerAuth'], $second->validatedSchemeNames);
    }

    #[Test]
    public function disjoint_handlers_accumulate_across_scheme_types_in_and_requirement(): void
    {
        $introspection = new IntrospectionStubHandler();
        $apiKey = new ApiKeyHeaderHandler();
        $validator = $this->buildValidatorWithHandlers(
            [self::BEARER, self::API_KEY],
            [[self::BEARER, self::API_KEY]],
            [$introspection, $apiKey],
        );

        $operation = $validator->validateRequest(
            $this->createRequest()
                ->withHeader('X-Introspected-Token', 'active-token')
                ->withHeader('X-Custom-Key', 'custom-secret'),
        );

        $this->assertSame('/secure', $operation->path);
        $this->assertSame(['bearerAuth'], $introspection->validatedSchemeNames);
        $this->assertSame(['headerApiKey'], $apiKey->validatedSchemeNames);
    }

    #[Test]
    public function custom_introspection_handler_is_wired_into_webhook_validation(): void
    {
        $handler = new IntrospectionStubHandler();
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::WEBHOOK_HANDLER_YAML)
            ->enableSecurityValidation()
            ->withSecuritySchemeHandler($handler)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('POST', '/webhooks/payment')
            ->withHeader('X-Introspected-Token', 'active-token');

        $operation = $validator->validateWebhook($request, 'payment.completed');

        $this->assertSame('payment.completed', $operation->path);
        $this->assertSame(['bearerAuth'], $handler->validatedSchemeNames);
    }

    #[Test]
    public function custom_introspection_handler_is_wired_into_callback_validation(): void
    {
        $handler = new IntrospectionStubHandler();
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CALLBACK_HANDLER_YAML)
            ->enableSecurityValidation()
            ->withSecuritySchemeHandler($handler)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('POST', '/callbacks/event')
            ->withHeader('X-Introspected-Token', 'active-token');

        $operation = $validator->validateCallback($request, 'subscriptionEvent');

        $this->assertSame('subscriptionEvent', $operation->path);
        $this->assertSame(['bearerAuth'], $handler->validatedSchemeNames);
    }

    private function partnerFor(string $schemeKey): string
    {
        return match ($schemeKey) {
            self::API_KEY => self::OTHER_API_KEY,
            default => self::API_KEY,
        };
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function credentialsFor(string $schemeKey): array
    {
        return match ($schemeKey) {
            self::API_KEY => [['X-API-Key', 'partner-secret']],
            self::OTHER_API_KEY => [['X-Other-Key', 'other-secret']],
        };
    }

    /**
     * @param list<string> $schemeKeys
     * @param list<list<string>> $requirementAlternatives
     */
    private function buildValidator(array $schemeKeys, array $requirementAlternatives): OpenApiValidatorInterface
    {
        return $this->buildValidatorWithHandlers($schemeKeys, $requirementAlternatives, []);
    }

    /**
     * @param list<string> $schemeKeys
     * @param list<list<string>> $requirementAlternatives
     * @param list<SecuritySchemeHandlerInterface> $handlers
     */
    private function buildValidatorWithHandlers(
        array $schemeKeys,
        array $requirementAlternatives,
        array $handlers,
    ): OpenApiValidatorInterface {
        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString($this->buildSpec($schemeKeys, $requirementAlternatives))
            ->enableSecurityValidation();

        foreach ($handlers as $handler) {
            $builder = $builder->withSecuritySchemeHandler($handler);
        }

        return $builder->build();
    }

    /**
     * @param list<string> $schemeKeys
     * @param list<list<string>> $requirementAlternatives
     */
    private function buildSpec(array $schemeKeys, array $requirementAlternatives): string
    {
        $schemes = '';

        foreach ($schemeKeys as $schemeKey) {
            $schemes .= self::SCHEME_YAML[$schemeKey];
        }

        $lines = [];

        foreach ($requirementAlternatives as $alternative) {
            foreach ($alternative as $position => $schemeName) {
                $lines[] = sprintf('%s%s: []', 0 === $position ? '        - ' : '          ', $schemeName);
            }
        }

        $security = implode("\n", $lines) . "\n";

        return sprintf(
            <<<'YAML'
            openapi: 3.1.0
            info:
              title: Security Scheme Handler Matrix API
              version: 1.0.0
            paths:
              /secure:
                get:
                  operationId: getSecure
                  security:
            %s      responses:
                    '200':
                      description: OK
            components:
              securitySchemes:
            %s
            YAML,
            $security,
            rtrim($schemes),
        );
    }

    /**
     * @param list<array{0: string, 1: string}> $credentials
     */
    private function applyCredentials(array $credentials): ServerRequestInterface
    {
        $request = $this->createRequest();

        foreach ($credentials as [$name, $value]) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    private function createRequest(): ServerRequestInterface
    {
        return $this->psrFactory->createServerRequest('GET', '/secure');
    }
}

final class IntrospectionStubHandler implements SecuritySchemeHandlerInterface
{
    /**
     * @var list<string>
     */
    public array $validatedSchemeNames = [];

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'http' === $scheme->type && 'bearer' === $scheme->scheme;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $this->validatedSchemeNames[] = $reporter->schemeName;

        if ('active-token' !== $request->getHeaderLine('X-Introspected-Token')) {
            return $reporter->reportMissing(
                sprintf('%s/%s', $scheme->type, $scheme->scheme),
                'X-Introspected-Token header',
            );
        }

        return null;
    }
}

final class HeaderRequirementHandler implements SecuritySchemeHandlerInterface
{
    /**
     * @var list<string>
     */
    public array $validatedSchemeNames = [];

    public function __construct(
        private readonly string $headerName,
    ) {}

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'http' === $scheme->type;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $this->validatedSchemeNames[] = $reporter->schemeName;

        if ('' === $request->getHeaderLine($this->headerName)) {
            return $reporter->reportMissing($scheme->type, sprintf('%s header', $this->headerName));
        }

        return null;
    }
}

final class ApiKeyHeaderHandler implements SecuritySchemeHandlerInterface
{
    /**
     * @var list<string>
     */
    public array $validatedSchemeNames = [];

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'apiKey' === $scheme->type;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $this->validatedSchemeNames[] = $reporter->schemeName;

        if ('' === $request->getHeaderLine('X-Custom-Key')) {
            return $reporter->reportMissing($scheme->type, 'X-Custom-Key header');
        }

        return null;
    }
}
