<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

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
}
