<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Validator\Link\Internal\RequestScope;
use Duyler\OpenApi\Validator\Link\Internal\ResponseScope;

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

    /**
     * Type-safe named-constructor splitting the LinkContext fields into
     * request-scope and response-scope. Prefer this over the constructor
     * at call-sites that supply fields from both scopes; the constructor
     * remains the canonical public API documented in the README.
     *
     * @see self::__construct()
     */
    public static function fromGroups(RequestScope $request, ResponseScope $response): self
    {
        return new self(
            body: $response->body,
            headers: $response->headers,
            queryParams: $response->queryParams,
            url: $request->url,
            method: $request->method,
            statusCode: $response->statusCode,
            pathParams: $request->pathParams,
            requestHeaders: $request->requestHeaders,
            requestBody: $request->requestBody,
        );
    }
}
