<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link\Internal;

/**
 * @internal
 */
final readonly class ExpressionData
{
    /**
     * @param array<string, mixed> $pathParams
     * @param array<string, mixed> $queryParams
     * @param array<string, string|list<string>> $requestHeaders
     * @param array<string, mixed> $body
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        public string $url = '',
        public string $method = '',
        public mixed $requestBody = null,
        public array $requestHeaders = [],
        public array $queryParams = [],
        public array $pathParams = [],
        public array $body = [],
        public array $headers = [],
        public ?int $statusCode = null,
    ) {}
}
