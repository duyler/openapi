<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Internal\MetadataUri;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** @internal */
final readonly class OAuth2Handler implements SecuritySchemeHandlerInterface
{
    public function __construct(
        private readonly BearerPresenceValidator $validator,
    ) {}

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'oauth2' === $scheme->type;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        if (false === MetadataUri::isValid($scheme->oauth2MetadataUrl)) {
            return $reporter->reportMissing($scheme->type, MetadataUri::invalidMessage('oauth2MetadataUrl'));
        }

        return $this->validator->validate($request, $scheme, $reporter);
    }
}
