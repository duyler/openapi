<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Schema;
use LogicException;
use Override;

final class ExternalSchemaBuilder extends OpenApiBuilder
{
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
