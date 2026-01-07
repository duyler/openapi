<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

readonly class ValidationErrorEvent
{
    public function __construct(
        public readonly ServerRequestInterface $request,
        public readonly string $path,
        public readonly string $method,
        public readonly ValidationException $exception,
    ) {}
}
