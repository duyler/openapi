<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Exception\RefResolutionException;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;

interface RefResolverInterface
{
    /**
     * Resolve $ref to actual schema
     *
     * @param string $ref JSON Pointer reference (e.g., '#/components/schemas/User')
     * @param OpenApiDocument $document Root document
     * @return Schema Resolved schema
     * @throws Exception\UnresolvableRefException
     */
    public function resolve(string $ref, OpenApiDocument $document): Schema;

    /**
     * Resolve $ref to actual parameter
     *
     * @param string $ref JSON Pointer reference (e.g., '#/components/parameters/LimitParam')
     * @param OpenApiDocument $document Root document
     * @return Parameter Resolved parameter
     * @throws Exception\UnresolvableRefException
     */
    public function resolveParameter(string $ref, OpenApiDocument $document): Parameter;

    /**
     * Resolve $ref to actual response
     *
     * @param string $ref JSON Pointer reference (e.g., '#/components/responses/SuccessResponse')
     * @param OpenApiDocument $document Root document
     * @return Response Resolved response
     * @throws Exception\UnresolvableRefException
     */
    public function resolveResponse(string $ref, OpenApiDocument $document): Response;

    /**
     * Check if schema contains discriminator (including nested references)
     *
     * @param Schema $schema Schema to check
     * @param OpenApiDocument $document Root document for resolving refs
     * @param array<int, bool> $visited Internal tracking to prevent infinite recursion
     * @return bool True if discriminator found, false otherwise
     */
    public function schemaHasDiscriminator(Schema $schema, OpenApiDocument $document, array &$visited = []): bool;

    /**
     * Get base URI from document's $self field
     *
     * @param OpenApiDocument $document Root document
     * @return string|null Base URI or null if not set
     */
    public function getBaseUri(OpenApiDocument $document): ?string;

    /**
     * Resolve relative reference using document's $self as base URI
     *
     * @param string $ref Relative reference (e.g., 'schemas/user.yaml')
     * @param OpenApiDocument $document Root document with $self field
     * @return string Absolute URI
     * @throws RefResolutionException If document has no $self field
     */
    public function resolveRelativeRef(string $ref, OpenApiDocument $document): string;

    /**
     * Combine base URI with relative reference
     *
     * @param string $baseUri Base URI (e.g., 'https://api.example.com/schemas/main.json')
     * @param string $relativeRef Relative reference (e.g., 'schemas/user.yaml')
     * @return string Combined absolute URI
     */
    public function combineUris(string $baseUri, string $relativeRef): string;

    /**
     * Resolve schema reference with summary/description override
     *
     * @param Schema $schema Schema with potential $ref and override values
     * @param OpenApiDocument $document Root document
     * @return Schema Resolved schema with overrides applied
     * @throws Exception\UnresolvableRefException
     */
    public function resolveSchemaWithOverride(Schema $schema, OpenApiDocument $document): Schema;

    /**
     * Resolve parameter reference with summary/description override
     *
     * @param Parameter $parameter Parameter with potential $ref and override values
     * @param OpenApiDocument $document Root document
     * @return Parameter Resolved parameter with overrides applied
     * @throws Exception\UnresolvableRefException
     */
    public function resolveParameterWithOverride(Parameter $parameter, OpenApiDocument $document): Parameter;

    /**
     * Resolve response reference with summary/description override
     *
     * @param Response $response Response with potential $ref and override values
     * @param OpenApiDocument $document Root document
     * @return Response Resolved response with overrides applied
     * @throws Exception\UnresolvableRefException
     */
    public function resolveResponseWithOverride(Response $response, OpenApiDocument $document): Response;
}
