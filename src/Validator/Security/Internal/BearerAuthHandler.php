<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** @internal */
final readonly class BearerAuthHandler implements SecuritySchemeHandlerInterface
{
    public function __construct(
        private readonly BearerAuthValidator $validator,
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
        return $this->validator->validate($request, $scheme, $reporter);
    }
}
