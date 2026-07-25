<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\UnsupportedSecuritySchemeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Psr\Http\Message\ServerRequestInterface;

use function strtolower;

/** @internal */
final readonly class BearerAuthValidator
{
    private const string BEARER_AUTH_PATTERN = '/^bearer\s+\S+\s*$/i';

    public function __construct(
        private readonly PregExecutor $pregExecutor,
    ) {}

    public function validate(ServerRequestInterface $request, SecurityScheme $scheme, SchemeCredentialReporter $reporter): ?MissingSecurityCredentialsError
    {
        $schemeType = strtolower($scheme->scheme ?? 'bearer');

        if ('bearer' !== $schemeType) {
            throw new UnsupportedSecuritySchemeException(
                schemeName: $reporter->schemeName,
                schemeType: 'http',
                httpScheme: $schemeType,
            );
        }

        $authorization = $request->getHeaderLine('Authorization');

        if ('' !== $authorization && 1 === $this->pregExecutor->match(self::BEARER_AUTH_PATTERN, $authorization)) {
            return null;
        }

        return $reporter->reportMissing('http/bearer', 'Authorization header');
    }
}
