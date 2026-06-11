<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ValidationErrorEvent
{
    public function __construct(
        public readonly ?ServerRequestInterface $request = null,
        public readonly string $path = '',
        public readonly string $method = '',
        public readonly ?ValidationException $exception = null,
        public readonly ?ResponseInterface $response = null,
        public readonly ?string $schemaRef = null,
    ) {}
}
