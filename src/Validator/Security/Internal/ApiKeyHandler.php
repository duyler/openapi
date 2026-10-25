<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/** @internal */
final readonly class ApiKeyHandler implements SecuritySchemeHandlerInterface
{
    public function __construct(
        private readonly ApiKeyValidator $validator,
    ) {}

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
        return $this->validator->validate($request, $scheme, $reporter);
    }
}
