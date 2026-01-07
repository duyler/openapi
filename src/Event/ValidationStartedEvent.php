<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

use Psr\Http\Message\ServerRequestInterface;

readonly class ValidationStartedEvent
{
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly string $path,
        public readonly string $method,
    ) {}
}
