<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Schema\Model\Schema;
use Psr\Cache\CacheItemPoolInterface;

readonly class CompilationCache
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly string $namespace = 'validator_compilation',
    ) {}

    public function get(string $schemaHash): ?string
    {
        $item = $this->pool->getItem($schemaHash);

        if (false === $item->isHit()) {
            return null;
        }

        $code = $item->get();

        if (false === is_string($code)) {
            return null;
        }

        return $code;
    }

    public function set(string $schemaHash, string $compiledCode): void
    {
        $item = $this->pool->getItem($schemaHash);
        $item->set($compiledCode);
        $item->expiresAfter(86400);

        $this->pool->save($item);
    }

    public function generateKey(Schema $schema): string
    {
        $hash = $this->calculateSchemaHash($schema);
        return $this->namespace . '.' . $hash;
    }

    private function calculateSchemaHash(Schema $schema): string
    {
        $data = $this->schemaToArray($schema);
        return hash('sha256', serialize($data));
    }

    private function schemaToArray(Schema $schema): array
    {
        return [
            'type' => $schema->type,
            'enum' => $schema->enum,
            'minLength' => $schema->minLength,
            'maxLength' => $schema->maxLength,
            'minimum' => $schema->minimum,
            'maximum' => $schema->maximum,
            'exclusiveMinimum' => $schema->exclusiveMinimum,
            'exclusiveMaximum' => $schema->exclusiveMaximum,
            'pattern' => $schema->pattern,
            'minItems' => $schema->minItems,
            'maxItems' => $schema->maxItems,
            'uniqueItems' => $schema->uniqueItems,
            'minProperties' => $schema->minProperties,
            'maxProperties' => $schema->maxProperties,
            'required' => $schema->required,
            'properties' => $schema->properties,
            'additionalProperties' => $schema->additionalProperties,
            'items' => $schema->items,
        ];
    }
}
