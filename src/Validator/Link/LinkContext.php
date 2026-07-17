<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

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
