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
final readonly class DigestAuthHandler implements SecuritySchemeHandlerInterface
{
    private const string DIGEST_AUTH_PATTERN = '/^Digest\s+(?<params>[0-9A-Za-z!#$%&\'*+.^_`|~-]+=(?:"(?:[^"\\\\]|\\\\.)*"|[0-9A-Za-z!#$%&\'*+.^_`|~-]+)(?:\s*,\s*[0-9A-Za-z!#$%&\'*+.^_`|~-]+=(?:"(?:[^"\\\\]|\\\\.)*"|[0-9A-Za-z!#$%&\'*+.^_`|~-]+))*)$/i';

    public function __construct(
        private readonly PregExecutor $pregExecutor,
    ) {}

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'http' === $scheme->type && 'digest' === $scheme->scheme;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $authorization = trim($request->getHeaderLine('Authorization'));

        try {
            $matched = $this->pregExecutor->match(self::DIGEST_AUTH_PATTERN, $authorization);
        } catch (PregRuntimeException) {
            $matched = 0;
        }

        if (1 !== $matched) {
            return $reporter->reportMissing('http/digest', 'Authorization header');
        }

        return null;
    }
}
