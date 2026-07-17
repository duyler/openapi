<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Schema\Model\Server;

/**
 * Immutable result of OpenAPI Link resolution.
 *
 * Replaces the previous array-shape return contract of LinkResolver::resolve()
 * to provide typed access to resolved parameters, request body, and the
 * optional server override declared by the Link object.
 */
final readonly class ResolvedLink
{
    /**
     * @param array<string, mixed> $parameters Resolved link parameters keyed by name
     * @param mixed $requestBody Resolved link request body (may be null, scalar, or structured)
     */
    public function __construct(
        public array $parameters,
        public mixed $requestBody,
        public ?Server $server,
    ) {}
}
