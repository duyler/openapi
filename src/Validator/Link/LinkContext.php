<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

/**
 * Holds all context data needed to resolve Runtime Expressions in OpenAPI Links.
 *
 * @param array<string, mixed> $body Deserialized response body
 * @param array<string, string> $headers Response headers
 * @param array<string, mixed> $queryParams Query parameters
 */
final readonly class LinkContext
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $queryParams
     */
    public function __construct(
        public array $body = [],
        public array $headers = [],
        public array $queryParams = [],
        public string $url = '',
        public string $method = '',
        public int $statusCode = 0,
    ) {}
}
