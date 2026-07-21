<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;

interface CompilationCacheInterface
{
    public function get(string $schemaHash): ?string;

    public function set(string $schemaHash, string $compiledCode): void;

    public function generateKey(Schema $schema, string $className, ?OpenApiDocument $document = null): string;
}
