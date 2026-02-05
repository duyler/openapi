<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

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
}
