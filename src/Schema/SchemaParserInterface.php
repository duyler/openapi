<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;

interface SchemaParserInterface
{
    /**
     * Parse OpenAPI specification from YAML or JSON content
     *
     * @param string $content OpenAPI specification content
     * @return OpenApiDocument Parsed document
     * @throws InvalidSchemaException
     */
    public function parse(string $content): OpenApiDocument;
}
