<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Validator\Link\Internal\RequestScope;
use Duyler\OpenApi\Validator\Link\Internal\ResponseScope;

final readonly class LinkContext
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $pathParams
     * @param array<string, string> $requestHeaders
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
