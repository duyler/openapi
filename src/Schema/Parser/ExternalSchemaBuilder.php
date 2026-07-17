<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Schema;
use LogicException;
use Override;

/**
 * Exposes SchemaBuilder::buildSchema() for callers that need to construct a
 * Schema from already-parsed data without going through the full
 * parse+buildDocument pipeline. Used by FileExternalRefResolver to convert
 * YAML/JSON payloads loaded from external files into Schema instances.
 */
final class ExternalSchemaBuilder extends OpenApiBuilder
{
    /**
     * Build a Schema from already-parsed data (array or boolean).
     *
     * @param mixed $data Parsed YAML/JSON payload for a single schema object.
     */
    public function buildSchemaFromData(mixed $data): Schema
    {
        return $this->context->schemaBuilder->buildSchema($data);
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        throw new LogicException('ExternalSchemaBuilder does not parse document content');
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'external-ref';
    }
}
