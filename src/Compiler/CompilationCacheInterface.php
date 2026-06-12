<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Schema\Model\Schema;

interface CompilationCacheInterface
{
    public function get(string $schemaHash): ?string;

    public function set(string $schemaHash, string $compiledCode): void;

    public function generateKey(Schema $schema): string;
}
