<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use Duyler\OpenApi\Validator\Exception\UnsupportedSecuritySchemeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Link\LinkContext;
use Duyler\OpenApi\Validator\Link\ResolvedLink;
use Duyler\OpenApi\Validator\Operation;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Deprecated;
use RuntimeException;

/**
 * OpenAPI validator interface
 *
 * Provides methods for validating PSR-7 HTTP messages against OpenAPI 3.2 specifications.
 * Operations are automatically detected from the request URI and method.
 */
interface OpenApiValidatorInterface
{
    /**
     * Get the OpenAPI document used by this validator.
     *
     * The document is loaded at build time and remains immutable for the
     * lifetime of the validator instance. Use this for SchemaRegistry,
     * introspection, or building routing maps.
     *
     * @return OpenApiDocument
     */
    public function getDocument(): OpenApiDocument;

    /**
     * Validate PSR-7 server request and return matched operation
     *
     * @param ServerRequestInterface $request HTTP request to validate
     * @return Operation Matched operation from OpenAPI specification
     * @throws ValidationException When the request violates the spec.
     * @throws OperationNotFoundException When no operation matches the request path/method.
     * @throws BuilderException When the validator cannot be built.
     * @throws UnsupportedSecuritySchemeException When the spec declares an unsupported security scheme type.
     * @throws BodyTooLargeException When the request body exceeds the configured size cap.
     * @throws RuntimeException For other infrastructure/security failures (UnresolvableRefException,
     *                           InvalidUtf8Exception, SchemaDepthExceededException, PregRuntimeException, etc.).
     */
    public function validateRequest(ServerRequestInterface $request): Operation;

    /**
     * Validate PSR-7 response against operation
     *
     * @param ResponseInterface $response HTTP response to validate
     * @param Operation $operation Operation to validate against
     * @throws ValidationException If validation fails
     * @throws BuilderException If operation not found in specification
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
     * Get validation errors as formatted string.
     *
     * @see ErrorFormatterInterface::formatException()
     *
     * @param ValidationException $e Validation exception containing errors
     * @return string Formatted error messages
     */
    #[Deprecated(message: <<<'TXT'
    Use ErrorFormatterInterface::formatException() instead.
                 This method will be removed in 2.0.
    TXT)]
    public function getFormattedErrors(ValidationException $e): string;

    /**
     * Validate webhook request against OpenAPI specification and return matched operation.
     *
     * @param ServerRequestInterface $request PSR-7 HTTP request
     * @param string $webhookName Webhook name from OpenAPI specification
     * @return Operation Matched operation from OpenAPI specification
     * @throws ValidationException If validation fails
     * @throws InvalidArgumentException If webhook name not found in specification
     */
    public function validateWebhook(ServerRequestInterface $request, string $webhookName): Operation;

    /**
     * Validate callback request against OpenAPI specification and return matched operation.
     *
     * @param ServerRequestInterface $request PSR-7 HTTP request
     * @param string $callbackName Callback name from OpenAPI specification
     * @return Operation Matched operation from OpenAPI specification
     * @throws ValidationException If validation fails
     * @throws InvalidArgumentException If callback name not found in specification
     */
    public function validateCallback(ServerRequestInterface $request, string $callbackName): Operation;

    /**
     * Resolve link parameters from response data.
     *
     * Builds a LinkContext with the response body as the only populated
     * field, so only $response.body expressions (and $url/$method/
     * $statusCode when supplied via the context) can be resolved. Use
     * resolveLinkWithContext() to supply full request and response
     * state for $request.* and $response.header/query expressions.
     *
     * @param string $linkName Link name from OpenAPI specification
     * @param array<string, mixed> $responseData Response data to extract values from
     * @throws InvalidArgumentException If the link name is not found in specification
     */
    public function resolveLink(string $linkName, array $responseData): ResolvedLink;

    /**
     * Resolve link parameters with full context for Runtime Expressions.
     *
     * Supports all OpenAPI 3.2 §6.19.2 runtime expressions: $url,
     * $method, $statusCode, $request.path.{name}, $request.query.{name},
     * $request.header.{name}, $request.body[#/{pointer}], $response.body
     * [#/{pointer}], $response.header[.{name}|#/pointer], and
     * $response.query[.{name}|#/pointer].
     *
     * @param string $linkName Link name from OpenAPI specification
     * @throws InvalidArgumentException If the link name is not found in specification
     */
    public function resolveLinkWithContext(string $linkName, LinkContext $context): ResolvedLink;

    /**
     * Reset internal state for hot-reload scenarios.
     *
     * Clears validator pool cache and ref resolver cache.
     * Safe to call between requests in long-running processes.
     */
    public function reset(): void;
}
