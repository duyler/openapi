<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;

interface RefResolverInterface
{
    /**
     * @param string $ref JSON Pointer reference (e.g., '#/components/schemas/User')
     * @param int $depth Current recursion depth
     * @throws Exception\UnresolvableRefException
     * @throws SchemaDepthExceededException
     */
    public function resolve(string $ref, OpenApiDocument $document, int $depth = 0): Schema;

    /**
     * @param string $ref JSON Pointer reference (e.g., '#/components/parameters/LimitParam')
     * @param int $depth Current recursion depth
     * @throws Exception\UnresolvableRefException
     * @throws SchemaDepthExceededException
     */
    public function resolveParameter(
        string $ref,
        OpenApiDocument $document,
        int $depth = 0,
    ): Parameter;

    /**
     * @param string $ref JSON Pointer reference (e.g., '#/components/responses/SuccessResponse')
     * @param int $depth Current recursion depth
     * @throws Exception\UnresolvableRefException
     * @throws SchemaDepthExceededException
     */
    public function resolveResponse(
        string $ref,
        OpenApiDocument $document,
        int $depth = 0,
    ): Response;

    /**
     * Check if schema contains discriminator (including nested references)
     *
     * @param OpenApiDocument $document Root document for resolving refs
     * @param int $depth Current recursion depth
     * @throws SchemaDepthExceededException
     */
    public function schemaHasDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        int $depth = 0,
    ): bool;

    /**
     * Check if schema or any of its nested schemas contains $ref
     *
     * @param int $depth Current recursion depth
     * @throws SchemaDepthExceededException
     */
    public function schemaHasRef(Schema $schema, int $depth = 0): bool;

    /**
     * Get base URI from document's $self field
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
    public function resolveRelativeRef(
        string $ref,
        OpenApiDocument $document,
    ): string;

    /**
     * @param string $baseUri Base URI (e.g., 'https://api.example.com/schemas/main.json')
     * @param string $relativeRef Relative reference (e.g., 'schemas/user.yaml')
     * @return string Combined absolute URI
     */
    public function combineUris(string $baseUri, string $relativeRef): string;

    /**
     * Resolve schema reference with summary/description override
     *
     * @param Schema $schema Schema with potential $ref and override values
     * @throws Exception\UnresolvableRefException
     */
    public function resolveSchemaWithOverride(
        Schema $schema,
        OpenApiDocument $document,
    ): Schema;

    /**
     * Resolve parameter reference with summary/description override
     *
     * @param Parameter $parameter Parameter with potential $ref and override values
     * @throws Exception\UnresolvableRefException
     */
    public function resolveParameterWithOverride(
        Parameter $parameter,
        OpenApiDocument $document,
    ): Parameter;

    /**
     * Resolve response reference with summary/description override
     *
     * @param Response $response Response with potential $ref and override values
     * @throws Exception\UnresolvableRefException
     */
    public function resolveResponseWithOverride(
        Response $response,
        OpenApiDocument $document,
    ): Response;

    /**
     * Recreates the WeakMap cache to free memory.
     */
    public function clear(): void;
}
