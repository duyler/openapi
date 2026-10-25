<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Validates credentials of a single security scheme during request validation.
 *
 * A handler claims the schemes it can validate through supports(); the registry
 * picks the first handler in list order (prepended handlers first) whose
 * supports() returns true, so overlapping claims are resolved by list order. Implementations
 * must stay stateless between calls to remain safe under concurrent request
 * validation.
 */
interface SecuritySchemeHandlerInterface
{
    public function supports(SecurityScheme $scheme): bool;

    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError;
}
