<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final readonly class ValidationEventPayload
{
    public function __construct(
        public readonly ?ServerRequestInterface $request,
        public readonly ?ResponseInterface $response,
        public readonly string $path,
        public readonly string $method,
        public readonly ?string $schemaRef = null,
    ) {}
}
