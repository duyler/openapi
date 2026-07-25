<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link\Internal;

/**
 * @internal
 */
final readonly class RequestScope
{
    /**
     * @param array<string, mixed>      $pathParams
     * @param array<string, string>     $requestHeaders
     */
    public function __construct(
        public readonly string $url = '',
        public readonly string $method = '',
        public readonly array $pathParams = [],
        public readonly array $requestHeaders = [],
        public readonly mixed $requestBody = null,
    ) {}
}
