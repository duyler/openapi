<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function trim;

/** @internal */
final readonly class BasicAuthHandler implements SecuritySchemeHandlerInterface
{
    private const string BASIC_AUTH_PATTERN = '/^Basic (?<token>[A-Za-z0-9+\/=_-]+)$/i';

    public function __construct(
        private readonly PregExecutor $pregExecutor,
    ) {}

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'http' === $scheme->type && 'basic' === $scheme->scheme;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $authorization = trim($request->getHeaderLine('Authorization'));

        try {
            $matched = $this->pregExecutor->match(self::BASIC_AUTH_PATTERN, $authorization);
        } catch (PregRuntimeException) {
            $matched = 0;
        }

        if (1 !== $matched) {
            return $reporter->reportMissing('http/basic', 'Authorization header');
        }

        return null;
    }
}
