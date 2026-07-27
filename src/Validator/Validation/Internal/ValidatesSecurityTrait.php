<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation\Internal;

use Duyler\OpenApi\Schema\Model\Operation as SchemaOperation;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Psr\Http\Message\ServerRequestInterface;

/** @internal */
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
