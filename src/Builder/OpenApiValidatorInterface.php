<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface OpenApiValidatorInterface
{
    /**
     * Validate PSR-7 server request
     *
     * @throws ValidationException
     */
    public function validateRequest(
        ServerRequestInterface $request,
        string $path,
        string $method,
    ): void;

    /**
     * Validate PSR-7 response
     *
     * @throws ValidationException
     */
    public function validateResponse(
        ResponseInterface $response,
        string $path,
        string $method,
    ): void;

    /**
     * Validate schema
     *
     * @throws ValidationException
     */
    public function validateSchema(mixed $data, string $schemaRef): void;

    /**
     * Get validation errors as formatted string
     */
    public function getFormattedErrors(ValidationException $e): string;
}
