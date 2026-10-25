<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Security\Internal\BasicAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\DigestAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\SchemeCredentialReporter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_fill;
use function implode;
use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

final class BasicDigestAuthHandlerTest extends TestCase
{
    private const string BASIC_CREDENTIAL = 'dXNlcjpwYXNz';

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function basic_handler_accepts_http_basic_scheme(): void
    {
        $handler = new BasicAuthHandler(new PregExecutor());

        $this->assertTrue($handler->supports(new SecurityScheme(type: 'http', scheme: 'basic')));
    }

    #[Test]
    public function basic_handler_rejects_other_schemes(): void
    {
        $handler = new BasicAuthHandler(new PregExecutor());

        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'digest')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'bearer')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key')));
    }

    #[Test]
    public function basic_handler_accepts_valid_credentials(): void
    {
        $error = $this->validateBasic('Basic ' . self::BASIC_CREDENTIAL, new NullLogger());

        $this->assertNull($error);
    }

    #[Test]
    public function basic_handler_accepts_case_insensitive_prefix_variants(): void
    {
        $lowercase = $this->validateBasic('basic ' . self::BASIC_CREDENTIAL, new NullLogger());
        $uppercase = $this->validateBasic('BASIC ' . self::BASIC_CREDENTIAL, new NullLogger());
        $mixed = $this->validateBasic('BaSiC ' . self::BASIC_CREDENTIAL, new NullLogger());

        $this->assertNull($lowercase);
        $this->assertNull($uppercase);
        $this->assertNull($mixed);
    }

    #[Test]
    public function basic_handler_accepts_base64_url_token_characters(): void
    {
        $padded = $this->validateBasic('Basic dXNlcjpzdHJvbmc+cGFzcw==', new NullLogger());
        $urlSafe = $this->validateBasic('Basic dXNlcjpwYXNz-_1', new NullLogger());

        $this->assertNull($padded);
        $this->assertNull($urlSafe);
    }

    #[Test]
    public function basic_handler_reports_missing_credentials_without_authorization_header(): void
    {
        $error = $this->validateBasic(null, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('basicAuth', $error->schemeName(reveal: true));
        $this->assertSame('http/basic', $error->schemeType(reveal: true));
        $this->assertSame('Authorization header', $error->location(reveal: true));
    }

    #[Test]
    public function basic_handler_reports_missing_credentials_for_empty_header(): void
    {
        $error = $this->validateBasic('', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('http/basic', $error->schemeType(reveal: true));
    }

    #[Test]
    public function basic_handler_reports_missing_credentials_for_scheme_word_without_token(): void
    {
        $bare = $this->validateBasic('Basic', new NullLogger());
        $trailingSpace = $this->validateBasic('Basic ', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $bare);
        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $trailingSpace);
    }

    #[Test]
    public function basic_handler_reports_missing_credentials_for_foreign_prefix(): void
    {
        $error = $this->validateBasic('Bearer ' . self::BASIC_CREDENTIAL, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('http/basic', $error->schemeType(reveal: true));
    }

    #[Test]
    public function basic_handler_reports_missing_credentials_for_token_with_invalid_characters(): void
    {
        $symbols = $this->validateBasic('Basic $$$$$', new NullLogger());
        $internalSpace = $this->validateBasic('Basic dXNl cjpwYXNz', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $symbols);
        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $internalSpace);
    }

    #[Test]
    public function basic_handler_converts_preg_backtrack_exhaustion_into_missing_credentials_error(): void
    {
        $adversarial = 'Basic ' . str_repeat('a', 15000) . '!';

        $error = $this->validateBasic($adversarial, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('http/basic', $error->schemeType(reveal: true));
    }

    #[Test]
    public function basic_handler_rejects_oversized_token_below_preg_limit_without_exception(): void
    {
        $boundary = 'Basic ' . str_repeat('a', 8000) . '!';

        $error = $this->validateBasic($boundary, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
    }

    #[Test]
    public function basic_handler_verbose_log_matches_bearer_shape_without_credentials(): void
    {
        $spy = new CredentialSpyLogger();

        $error = $this->validateBasic('Bearer ' . self::BASIC_CREDENTIAL, $spy);

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertCount(1, $spy->records);
        $this->assertSame('debug', $spy->records[0]['level']);
        $this->assertSame('Security validation failed', $spy->records[0]['message']);
        $this->assertSame([
            'schemeName' => 'basicAuth',
            'schemeType' => 'http/basic',
            'location' => 'Authorization header',
        ], $spy->records[0]['context']);
    }

    #[Test]
    public function basic_handler_log_and_error_never_contain_credential_values(): void
    {
        $spy = new CredentialSpyLogger();
        $header = 'Basic ' . self::BASIC_CREDENTIAL . ' <script>alert(1)</script>';

        $error = $this->validateBasic($header, $spy);

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertStringNotContainsString(self::BASIC_CREDENTIAL, $error->getMessage());
        foreach ($spy->records as $record) {
            $this->assertStringNotContainsString(self::BASIC_CREDENTIAL, $record['message']);
            $this->assertStringNotContainsString(self::BASIC_CREDENTIAL, json_encode($record['context'], JSON_THROW_ON_ERROR));
        }
    }

    #[Test]
    public function digest_handler_accepts_http_digest_scheme(): void
    {
        $handler = new DigestAuthHandler(new PregExecutor());

        $this->assertTrue($handler->supports(new SecurityScheme(type: 'http', scheme: 'digest')));
    }

    #[Test]
    public function digest_handler_rejects_other_schemes(): void
    {
        $handler = new DigestAuthHandler(new PregExecutor());

        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'basic')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http', scheme: 'bearer')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'http')));
        $this->assertFalse($handler->supports(new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key')));
    }

    #[Test]
    public function digest_handler_accepts_unquoted_parameter_list(): void
    {
        $error = $this->validateDigest('Digest username=x, realm=y, nonce=z', new NullLogger());

        $this->assertNull($error);
    }

    #[Test]
    public function digest_handler_accepts_quoted_parameter_values(): void
    {
        $error = $this->validateDigest(
            'Digest username="Mufasa", realm="testrealm@host.com", nonce="abc123", qop=auth',
            new NullLogger(),
        );

        $this->assertNull($error);
    }

    #[Test]
    public function digest_handler_accepts_escaped_quotes_inside_quoted_values(): void
    {
        $error = $this->validateDigest('Digest username="a\"b", nonce=z', new NullLogger());

        $this->assertNull($error);
    }

    #[Test]
    public function digest_handler_accepts_list_without_spaces_after_commas(): void
    {
        $error = $this->validateDigest('Digest username=x,realm=y', new NullLogger());

        $this->assertNull($error);
    }

    #[Test]
    public function digest_handler_accepts_case_insensitive_prefix_variants(): void
    {
        $lowercase = $this->validateDigest('digest username=x', new NullLogger());
        $uppercase = $this->validateDigest('DIGEST username=x', new NullLogger());
        $mixed = $this->validateDigest('DiGeSt username=x', new NullLogger());

        $this->assertNull($lowercase);
        $this->assertNull($uppercase);
        $this->assertNull($mixed);
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_without_authorization_header(): void
    {
        $error = $this->validateDigest(null, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('digestAuth', $error->schemeName(reveal: true));
        $this->assertSame('http/digest', $error->schemeType(reveal: true));
        $this->assertSame('Authorization header', $error->location(reveal: true));
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_for_scheme_word_without_parameters(): void
    {
        $bare = $this->validateDigest('Digest', new NullLogger());
        $trailingSpace = $this->validateDigest('Digest ', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $bare);
        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $trailingSpace);
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_for_parameter_without_value(): void
    {
        $error = $this->validateDigest('Digest username', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_for_empty_parameter_value(): void
    {
        $error = $this->validateDigest('Digest username=, realm=y', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_for_double_comma(): void
    {
        $error = $this->validateDigest('Digest username=x,, realm=y', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_for_unquoted_reserved_value_characters(): void
    {
        $error = $this->validateDigest('Digest uri=/dir/index.html', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
    }

    #[Test]
    public function digest_handler_converts_pcre_jit_stack_exhaustion_into_missing_credentials_error(): void
    {
        $adversarial = 'Digest a="' . str_repeat('a', 20000);

        $error = $this->validateDigest($adversarial, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('http/digest', $error->schemeType(reveal: true));
    }

    #[Test]
    public function digest_handler_converts_backtrack_exhaustion_into_missing_credentials_error(): void
    {
        $adversarial = 'Digest ' . implode(',', array_fill(0, 2000, 'k=v'));

        $error = $this->validateDigest($adversarial, new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
    }

    #[Test]
    public function digest_handler_reports_missing_credentials_for_foreign_prefix(): void
    {
        $error = $this->validateDigest('Bearer abc123', new NullLogger());

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertSame('http/digest', $error->schemeType(reveal: true));
    }

    #[Test]
    public function digest_handler_verbose_log_matches_bearer_shape_without_credentials(): void
    {
        $spy = new CredentialSpyLogger();

        $error = $this->validateDigest('Digest username', $spy);

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertCount(1, $spy->records);
        $this->assertSame('debug', $spy->records[0]['level']);
        $this->assertSame('Security validation failed', $spy->records[0]['message']);
        $this->assertSame([
            'schemeName' => 'digestAuth',
            'schemeType' => 'http/digest',
            'location' => 'Authorization header',
        ], $spy->records[0]['context']);
    }

    #[Test]
    public function digest_handler_log_and_error_never_contain_credential_values(): void
    {
        $spy = new CredentialSpyLogger();
        $header = 'Digest username="Mufasa", nonce=abc123 trailing';

        $error = $this->validateDigest($header, $spy);

        $this->assertInstanceOf(MissingSecurityCredentialsError::class, $error);
        $this->assertStringNotContainsString('Mufasa', $error->getMessage());
        $this->assertStringNotContainsString('abc123', $error->getMessage());
        foreach ($spy->records as $record) {
            $this->assertStringNotContainsString('Mufasa', $record['message']);
            $this->assertStringNotContainsString('abc123', $record['message']);
            $context = json_encode($record['context'], JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('Mufasa', $context);
            $this->assertStringNotContainsString('abc123', $context);
        }
    }

    private function validateBasic(?string $header, LoggerInterface $logger): ?MissingSecurityCredentialsError
    {
        return new BasicAuthHandler(new PregExecutor())->validate(
            $this->makeRequest($header),
            new SecurityScheme(type: 'http', scheme: 'basic'),
            new SchemeCredentialReporter('basicAuth', $logger),
        );
    }

    private function validateDigest(?string $header, LoggerInterface $logger): ?MissingSecurityCredentialsError
    {
        return new DigestAuthHandler(new PregExecutor())->validate(
            $this->makeRequest($header),
            new SecurityScheme(type: 'http', scheme: 'digest'),
            new SchemeCredentialReporter('digestAuth', $logger),
        );
    }

    private function makeRequest(?string $header): ServerRequestInterface
    {
        $request = $this->factory->createServerRequest('GET', '/users');

        if (null !== $header) {
            $request = $request->withHeader('Authorization', $header);
        }

        return $request;
    }
}

/**
 * @internal
 */
final class CredentialSpyLogger implements LoggerInterface
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
