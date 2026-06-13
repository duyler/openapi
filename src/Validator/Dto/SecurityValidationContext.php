<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SecurityValidationContext
{
    /**
     * @param array<string, SecurityScheme> $securitySchemes
     */
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly string $path,
        public readonly string $method,
        public readonly SecurityRequirement $securityRequirements,
        public readonly array $securitySchemes,
    ) {}
}
