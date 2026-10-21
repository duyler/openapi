<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ValidationFinishedEvent
{
    public function __construct(
        public readonly ?ServerRequestInterface $request = null,
        public readonly string $path = '',
        public readonly string $method = '',
        public readonly bool $isSuccess = true,
        public readonly float $durationInSeconds = 0.0,
        public readonly ?ResponseInterface $response = null,
        public readonly ?string $schemaRef = null,
    ) {}
}
