<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation\Internal;

use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared `validateSecurity()` implementation for runtime validators that
 * surface document-level or operation-level security requirements against
 * a request. Eliminates the previously duplicated 24-line body that existed
 * in both {@see CallbackValidator} and {@see WebhookValidator}.
 *
 * Consumers must expose two readable properties:
 *   - ValidatorDependencies `$context`
 *   - SecurityValidator       `$securityValidator`
 *
 * @internal
 */
trait ValidatesSecurityTrait
{
    private function validateSecurity(
        ServerRequestInterface $request,
        SchemaOperation $operation,
        string $path,
        string $method,
    ): void {
        $securityRequirements = $operation->security ?? $this->context->document->security;

        if (null === $securityRequirements) {
            return;
        }

        $securitySchemes = $this->context->document->components?->securitySchemes ?? [];

        $securityContext = new SecurityValidationContext(
            request: $request,
            path: $path,
            method: $method,
            securityRequirements: $securityRequirements,
            securitySchemes: $securitySchemes,
        );

        $this->securityValidator->validate($securityContext);
    }
}
