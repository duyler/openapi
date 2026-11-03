<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\RequestBody;
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
     * Resolve the schema referenced by $ref without following the target's own
     * `$ref` chain; `$ref` sibling keywords of the resolved target stay intact
     * and are applied by the validation-time ref resolution with sibling merge
     *
     * @param string $ref JSON Pointer reference (e.g., '#/components/schemas/User')
     * @throws Exception\UnresolvableRefException
     * @throws SchemaDepthExceededException
     */
    public function resolveSchemaEntry(string $ref, OpenApiDocument $document): Schema;

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
     * @param string $ref JSON Pointer reference (e.g., '#/components/requestBodies/UserBody')
     * @param int $depth Current recursion depth
     * @throws Exception\UnresolvableRefException
     * @throws SchemaDepthExceededException
     */
    public function resolveRequestBody(
        string $ref,
        OpenApiDocument $document,
        int $depth = 0,
    ): RequestBody;

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
     * @param string|null $baseUri Absolute base URI of the resource enclosing the schema;
     *     null keeps the schema's own `$id` (or the document `$self`) as the base
     * @throws Exception\UnresolvableRefException
     */
    public function resolveSchemaWithOverride(
        Schema $schema,
        OpenApiDocument $document,
        ?string $baseUri = null,
    ): Schema;

    /**
     * Same resolution as resolveSchemaWithOverride, additionally returning every
     * `$id`-identified resource schema crossed by the `$ref` chain (outermost
     * first) so that callers can push them onto the dynamic scope
     *
     * @param string|null $baseUri Absolute base URI of the resource enclosing the schema
     * @return array{Schema, list<Schema>}
     * @throws Exception\UnresolvableRefException
     */
    public function resolveSchemaWithOverrideAndChain(
        Schema $schema,
        OpenApiDocument $document,
        ?string $baseUri = null,
    ): array;

    /**
     * Resolve the `$ref` target without merging sibling keywords into it, returning the
     * sibling schema (with the `$ref` field dropped) as a separate companion application
     * whenever the dialect applies reference siblings
     *
     * @param string|null $baseUri Absolute base URI of the resource enclosing the schema
     * @return array{Schema, Schema|null, list<Schema>} Resolved target, companion sibling, chain resources
     * @throws Exception\UnresolvableRefException
     */
    public function resolveSchemaWithCompanionAndChain(
        Schema $schema,
        OpenApiDocument $document,
        ?string $baseUri = null,
    ): array;

    /**
     * Fold the dynamic scope frames (outermost first) into the absolute base URI
     * of the innermost frame resource, anchored at the document `$self`
     *
     * @param list<Schema> $frames
     */
    public function scopeBaseUri(array $frames, OpenApiDocument $document): ?string;

    /**
     * Find the outermost `$dynamicAnchor` with the given name across the dynamic
     * scope frames: each frame is matched by its root anchor first, then by any
     * `$dynamicAnchor` nested inside the frame resource (per-resource lookup)
     *
     * @param list<Schema> $frames
     * @throws Exception\UnresolvableRefException
     */
    public function findDynamicAnchorInScope(array $frames, string $name, OpenApiDocument $document): ?Schema;

    /**
     * All documents registered for cross-document resolution, in registration order
     *
     * @return list<OpenApiDocument>
     */
    public function registeredDocuments(): array;

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
     * Resolve request body reference with summary/description override
     *
     * @param RequestBody $requestBody Request body with potential $ref and override values
     * @throws Exception\UnresolvableRefException
     */
    public function resolveRequestBodyWithOverride(
        RequestBody $requestBody,
        OpenApiDocument $document,
    ): RequestBody;

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
