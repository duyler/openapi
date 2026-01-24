<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Psr15\Operation;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * OpenAPI validator interface
 *
 * Provides methods for validating PSR-7 HTTP messages against OpenAPI 3.1 specifications.
 * Operations are automatically detected from the request URI and method.
 */
interface OpenApiValidatorInterface
{
    /**
     * Validate PSR-7 server request and return matched operation
     *
     * @param ServerRequestInterface $request HTTP request to validate
     * @return Operation Matched operation from OpenAPI specification
     * @throws ValidationException If validation fails
     * @throws BuilderException If operation not found in specification
     */
    public function validateRequest(ServerRequestInterface $request): Operation;

    /**
     * Validate PSR-7 response against operation
     *
     * @param ResponseInterface $response HTTP response to validate
     * @param Operation $operation Operation to validate against
     * @throws ValidationException If validation fails
     */
    public function validateResponse(ResponseInterface $response, Operation $operation): void;

    /**
     * Validate data against schema
     *
     * @param mixed $data Data to validate
     * @param string $schemaRef Schema reference path (e.g., "#/components/schemas/User")
     * @throws ValidationException If validation fails
     */
    public function validateSchema(mixed $data, string $schemaRef): void;

    /**
     * Get validation errors as formatted string
     *
     * @param ValidationException $e Validation exception containing errors
     * @return string Formatted error messages
     */
    public function getFormattedErrors(ValidationException $e): string;
}
