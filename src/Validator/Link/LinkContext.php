<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

/**
 * Carries the runtime context required to evaluate OpenAPI 3.2 §6.19.2
 * Runtime Expressions inside Link parameters and request bodies.
 *
 * The same queryParams array backs both $request.query and $response.query
 * expressions since the underlying query string is shared.
 */
final readonly class LinkContext
{
    /**
     * @param array<string, mixed> $body Response body data (decoded JSON or equivalent)
     * @param array<string, string> $headers Response headers (header name => value)
     * @param array<string, mixed> $queryParams Query string parameters shared by request and response scope
     * @param array<string, mixed> $pathParams Request path parameters (path template variable => value)
     * @param array<string, string> $requestHeaders Request headers (header name => value, case-insensitive lookup)
     */
    public function __construct(
        public array $body = [],
        public array $headers = [],
        public array $queryParams = [],
        public string $url = '',
        public string $method = '',
        public int $statusCode = 0,
        public array $pathParams = [],
        public array $requestHeaders = [],
        public mixed $requestBody = null,
    ) {}
}
