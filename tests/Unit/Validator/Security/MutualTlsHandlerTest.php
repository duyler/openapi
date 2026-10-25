<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Security\Internal\MutualTlsHandler;
use Duyler\OpenApi\Validator\Security\Internal\SchemeCredentialReporter;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function array_filter;
use function array_values;

final class MutualTlsHandlerTest extends TestCase
{
    private const string WARNING_MESSAGE = 'mutualTLS presence detected via proxy header; trusting proxy configuration required';

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function handler_accepts_only_mutual_tls_scheme(): void
    {
        $handler = $this->makeHandler(new MutualTlsSpyLogger());

        $this->assertTrue($handler->supports(new SecurityScheme(type: 'mutualTLS')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'bearer')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'oauth2')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'openIdConnect')));
    }

    #[Test]
    public function handler_passes_with_ssl_client_cert_header_and_warns_about_proxy_trust(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);

        $error = $handler->validate(
            $this->makeRequest(['SSL_CLIENT_CERT' => '-----BEGIN CERTIFICATE-----']),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertSame([
            ['level' => 'warning', 'message' => self::WARNING_MESSAGE, 'context' => []],
        ], $spy->records);
    }

    #[Test]
    public function handler_passes_with_x_ssl_cert_header(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);

        $error = $handler->validate(
            $this->makeRequest(['X-SSL-CERT' => 'certificate-body']),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertCount(1, $spy->records);
        $this->assertSame('warning', $spy->records[0]['level']);
    }

    #[Test]
    public function handler_passes_with_x_client_cert_header(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);

        $error = $handler->validate(
            $this->makeRequest(['X-Client-Cert' => 'certificate-body']),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertCount(1, $spy->records);
        $this->assertSame('warning', $spy->records[0]['level']);
    }

    #[Test]
    public function handler_header_lookup_is_case_insensitive(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);

        $error = $handler->validate(
            $this->makeRequest(['x-client-cert' => 'certificate-body']),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        $this->assertCount(1, $spy->records);
    }

    #[Test]
    public function handler_warns_on_every_triggering_validation(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);
        $scheme = new SecurityScheme(type: 'mutualTLS');
        $request = $this->makeRequest(['SSL_CLIENT_CERT' => 'certificate-body']);

        $first = $handler->validate($request, $scheme, $this->makeReporter($spy));
        $second = $handler->validate($request, $scheme, $this->makeReporter($spy));

        $this->assertNull($first);
        $this->assertNull($second);
        $this->assertCount(2, $spy->records);
        $this->assertSame('warning', $spy->records[0]['level']);
        $this->assertSame('warning', $spy->records[1]['level']);
    }

    #[Test]
    public function handler_reports_missing_credentials_without_certificate_headers(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);

        $error = $handler->validate(
            $this->makeRequest([]),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('mutualTLSAuth', $error->schemeName(reveal: true));
        $this->assertSame('mutualTLS', $error->schemeType(reveal: true));

        $warnings = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'warning' === $record['level'],
        ));
        $this->assertSame([], $warnings, 'No warning must be emitted when the heuristic does not trigger.');
    }

    #[Test]
    public function handler_missing_credentials_log_keeps_bearer_shape_without_certificate_values(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);

        $error = $handler->validate(
            $this->makeRequest([]),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame([
            'schemeName' => 'mutualTLSAuth',
            'schemeType' => 'mutualTLS',
            'location' => 'TLS client certificate headers',
        ], $spy->records[0]['context']);
    }

    #[Test]
    public function handler_warning_never_contains_certificate_values(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);
        $certificate = '-----BEGIN CERTIFICATE----- secret-fingerprint -----END CERTIFICATE-----';

        $error = $handler->validate(
            $this->makeRequest(['SSL_CLIENT_CERT' => $certificate]),
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertNull($error);
        foreach ($spy->records as $record) {
            $this->assertStringNotContainsString($certificate, $record['message']);
            $this->assertStringNotContainsString('secret-fingerprint', $record['message']);
        }
    }

    #[Test]
    public function handler_ignores_server_params_and_checks_request_headers_only(): void
    {
        $spy = new MutualTlsSpyLogger();
        $handler = $this->makeHandler($spy);
        $request = $this->factory->createServerRequest('GET', '/users', ['SSL_CLIENT_CERT' => 'certificate-body']);

        $error = $handler->validate(
            $request,
            new SecurityScheme(type: 'mutualTLS'),
            $this->makeReporter($spy),
        );

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);

        $warnings = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'warning' === $record['level'],
        ));
        $this->assertSame([], $warnings);
    }

    #[Test]
    public function validator_passes_its_logger_to_the_mutual_tls_handler(): void
    {
        $spy = new MutualTlsSpyLogger();
        $validator = new SecurityValidator($spy);
        $request = $this->factory->createServerRequest('GET', '/users')
            ->withHeader('X-SSL-CERT', 'certificate-body');
        $securityRequirements = new SecurityRequirement([
            ['mutualTLS' => []],
        ]);
        $securitySchemes = [
            'mutualTLS' => new SecurityScheme(type: 'mutualTLS'),
        ];

        $validator->validate(new SecurityValidationContext(
            request: $request,
            path: '/users',
            method: 'GET',
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        ));

        $warnings = array_values(array_filter(
            $spy->records,
            static fn(array $record): bool => 'warning' === $record['level'],
        ));
        $this->assertCount(1, $warnings);
        $this->assertSame(self::WARNING_MESSAGE, $warnings[0]['message']);
    }

    private function makeHandler(LoggerInterface $logger): MutualTlsHandler
    {
        return new MutualTlsHandler($logger);
    }

    private function makeReporter(LoggerInterface $logger): SchemeCredentialReporter
    {
        return new SchemeCredentialReporter('mutualTLSAuth', $logger);
    }

    /**
     * @param array<string, string> $headers
     */
    private function makeRequest(array $headers): ServerRequestInterface
    {
        $request = $this->factory->createServerRequest('GET', '/users');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }
}

/**
 * @internal
 */
final class MutualTlsSpyLogger implements LoggerInterface
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
