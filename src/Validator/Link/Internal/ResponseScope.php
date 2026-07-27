<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link\Internal;

/** @internal */
final readonly class ResponseScope
{
    /**
     * @param array<string, mixed>      $body
     * @param array<string, string>     $headers
     * @param array<string, mixed>      $queryParams
     */
    public function __construct(
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $queryParams = [],
        public readonly int $statusCode = 0,
    ) {}
}
