<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Security\Internal\ApiKeyHandler;
use Duyler\OpenApi\Validator\Security\Internal\BasicAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\BearerAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\DigestAuthHandler;
use Duyler\OpenApi\Validator\Security\Internal\SchemeCredentialReporter;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;

use function count;

final class SecuritySchemeHandlerRegistryTest extends TestCase
{
    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function build_in_registers_builtin_handlers_in_canonical_order(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());

        $handlers = $registry->all();

        self::assertCount(4, $handlers);
        self::assertInstanceOf(BasicAuthHandler::class, $handlers[0]);
        self::assertInstanceOf(DigestAuthHandler::class, $handlers[1]);
        self::assertInstanceOf(BearerAuthHandler::class, $handlers[2]);
        self::assertInstanceOf(ApiKeyHandler::class, $handlers[3]);
    }

    #[Test]
    public function build_in_finds_basic_handler_for_http_basic_scheme(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());

        $handler = $registry->findSupporting(new SecurityScheme(type: 'http', scheme: 'basic'));

        self::assertInstanceOf(BasicAuthHandler::class, $handler);
    }

    #[Test]
    public function build_in_finds_digest_handler_for_http_digest_scheme(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());

        $handler = $registry->findSupporting(new SecurityScheme(type: 'http', scheme: 'digest'));

        self::assertInstanceOf(DigestAuthHandler::class, $handler);
    }

    #[Test]
    public function build_in_finds_bearer_handler_for_http_bearer_scheme(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());

        $handler = $registry->findSupporting(new SecurityScheme(type: 'http', scheme: 'bearer'));

        self::assertInstanceOf(BearerAuthHandler::class, $handler);
    }

    #[Test]
    public function build_in_finds_api_key_handler_for_api_key_scheme(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());

        $handler = $registry->findSupporting(new SecurityScheme(type: 'apiKey', in: 'header', name: 'X-API-Key'));

        self::assertInstanceOf(ApiKeyHandler::class, $handler);
    }

    #[Test]
    public function find_supporting_returns_null_for_unsupported_scheme_types(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());

        self::assertNull($registry->findSupporting(new SecurityScheme(type: 'mutualTLS')));
        self::assertNull($registry->findSupporting(new SecurityScheme(type: 'oauth2')));
        self::assertNull($registry->findSupporting(new SecurityScheme(type: 'openIdConnect')));
    }

    #[Test]
    public function empty_registry_finds_no_handler(): void
    {
        $registry = new SecuritySchemeHandlerRegistry();

        self::assertSame([], $registry->all());
        self::assertNull($registry->findSupporting(new SecurityScheme(type: 'http', scheme: 'bearer')));
    }

    #[Test]
    public function with_puts_custom_handler_ahead_of_the_builtin_set(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());
        $custom = new RecordingSecuritySchemeHandler('http');

        $extended = $registry->with($custom);

        $handlers = $extended->all();
        self::assertCount(5, $handlers);
        self::assertSame($custom, $handlers[0]);
        self::assertInstanceOf(BasicAuthHandler::class, $handlers[1]);
        self::assertInstanceOf(DigestAuthHandler::class, $handlers[2]);
        self::assertInstanceOf(BearerAuthHandler::class, $handlers[3]);
        self::assertInstanceOf(ApiKeyHandler::class, $handlers[4]);
        self::assertSame($custom, $extended->findSupporting(new SecurityScheme(type: 'http', scheme: 'bearer')));
    }

    #[Test]
    public function custom_handler_wins_lookup_for_a_type_without_a_builtin(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());
        $custom = new RecordingSecuritySchemeHandler('mutualTLS');

        $extended = $registry->with($custom);

        self::assertSame($custom, $extended->findSupporting(new SecurityScheme(type: 'mutualTLS')));
        self::assertInstanceOf(ApiKeyHandler::class, $extended->findSupporting(new SecurityScheme(type: 'apiKey')));
    }

    #[Test]
    public function substituted_handler_is_called_instead_of_the_builtin_one(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());
        $custom = new RecordingSecuritySchemeHandler('http');
        $extended = $registry->with($custom);
        $scheme = new SecurityScheme(type: 'http', scheme: 'bearer');

        $handler = $extended->findSupporting($scheme);
        $error = $handler->validate(
            $this->factory->createServerRequest('GET', '/users'),
            $scheme,
            new SchemeCredentialReporter('bearerAuth', new NullLogger()),
        );

        self::assertSame([$scheme], $custom->validated, 'The substitute handler must handle the scheme.');
        self::assertNull($error);
    }

    #[Test]
    public function with_keeps_original_registry_unchanged(): void
    {
        $registry = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());
        $custom = new RecordingSecuritySchemeHandler('http');

        $extended = $registry->with($custom);

        self::assertNotSame($registry, $extended);
        self::assertCount(4, $registry->all());
        self::assertInstanceOf(
            BearerAuthHandler::class,
            $registry->findSupporting(new SecurityScheme(type: 'http', scheme: 'bearer')),
        );
    }

    #[Test]
    public function with_base_keeps_user_handlers_ahead_of_base_handlers(): void
    {
        $base = SecuritySchemeHandlerRegistry::buildIn(new PregExecutor());
        $custom = new RecordingSecuritySchemeHandler('http');
        $user = new SecuritySchemeHandlerRegistry()->with($custom);

        $merged = $user->withBase($base);

        self::assertSame($custom, $merged->findSupporting(new SecurityScheme(type: 'http', scheme: 'bearer')));
        self::assertInstanceOf(ApiKeyHandler::class, $merged->findSupporting(new SecurityScheme(type: 'apiKey')));
        self::assertCount(5, $merged->all());
        self::assertSame(count($user->all()) + count($base->all()), count($merged->all()));
    }
}

final class RecordingSecuritySchemeHandler implements SecuritySchemeHandlerInterface
{
    /** @var list<SecurityScheme> */
    public array $validated = [];

    public function __construct(
        private readonly string $supportedType,
    ) {}

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return $this->supportedType === $scheme->type;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $this->validated[] = $scheme;

        return null;
    }
}
