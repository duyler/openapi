<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Override;
use Psr\Cache\CacheItemPoolInterface;

use function is_string;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class CompilationCache implements CompilationCacheInterface
{
    private const int DEFAULT_CACHE_TTL = 86400;

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly string $namespace = 'validator_compilation',
    ) {}

    #[Override]
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

    #[Override]
    public function set(string $schemaHash, string $compiledCode): void
    {
        $item = $this->pool->getItem($schemaHash);
        $item->set($compiledCode);
        $item->expiresAfter(self::DEFAULT_CACHE_TTL);

        $this->pool->save($item);
    }

    #[Override]
    public function generateKey(Schema $schema): string
    {
        $hash = $this->calculateSchemaHash($schema);
        return $this->namespace . '.' . $hash;
    }

    private function calculateSchemaHash(Schema $schema): string
    {
        $data = $this->schemaToArray($schema);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function schemaToArray(Schema $schema): array
    {
        $data = [
            'ref' => $schema->ref,
            'refSummary' => $schema->refSummary,
            'refDescription' => $schema->refDescription,
            'format' => $schema->format,
            'title' => $schema->title,
            'description' => $schema->description,
            'default' => $schema->hasDefault ? $schema->default : null,
            'hasDefault' => $schema->hasDefault,
            'deprecated' => $schema->deprecated,
            'readOnly' => $schema->readOnly,
            'writeOnly' => $schema->writeOnly,
            'type' => $schema->type,
            'nullable' => $schema->nullable,
            'const' => $schema->hasConst ? $schema->const : null,
            'hasConst' => $schema->hasConst,
            'multipleOf' => $schema->multipleOf,
            'maximum' => $schema->maximum,
            'exclusiveMaximum' => $schema->exclusiveMaximum,
            'minimum' => $schema->minimum,
            'exclusiveMinimum' => $schema->exclusiveMinimum,
            'maxLength' => $schema->maxLength,
            'minLength' => $schema->minLength,
            'pattern' => $schema->pattern,
            'maxItems' => $schema->maxItems,
            'minItems' => $schema->minItems,
            'uniqueItems' => $schema->uniqueItems,
            'maxProperties' => $schema->maxProperties,
            'minProperties' => $schema->minProperties,
            'required' => $schema->required,
            'discriminator' => $this->discriminatorToArray($schema->discriminator),
            'propertyNames' => $this->schemaToArrayOrNull($schema->propertyNames),
            'unevaluatedProperties' => $this->additionalPropertiesToArray($schema->unevaluatedProperties),
            'unevaluatedItems' => $this->schemaToArrayOrNull($schema->unevaluatedItems),
            'contains' => $this->schemaToArrayOrNull($schema->contains),
            'minContains' => $schema->minContains,
            'maxContains' => $schema->maxContains,
            'if' => $this->schemaToArrayOrNull($schema->if),
            'then' => $this->schemaToArrayOrNull($schema->then),
            'else' => $this->schemaToArrayOrNull($schema->else),
            'example' => $schema->example,
            'examples' => $schema->examples,
            'contentEncoding' => $schema->contentEncoding,
            'contentMediaType' => $schema->contentMediaType,
            'contentSchema' => $this->additionalPropertiesToArray($schema->contentSchema),
            'jsonSchemaDialect' => $schema->jsonSchemaDialect,
            'xml' => $this->xmlToArray($schema->xml),
        ];

        $data['allOf'] = $this->schemaListToArray($schema->allOf);
        $data['anyOf'] = $this->schemaListToArray($schema->anyOf);
        $data['oneOf'] = $this->schemaListToArray($schema->oneOf);
        $data['not'] = $this->schemaToArrayOrNull($schema->not);
        $data['properties'] = $this->schemaMapToArray($schema->properties);
        $data['additionalProperties'] = $this->additionalPropertiesToArray($schema->additionalProperties);
        $data['items'] = $this->schemaToArrayOrNull($schema->items);
        $data['prefixItems'] = $this->schemaListToArray($schema->prefixItems);
        $data['patternProperties'] = $this->schemaMapToArray($schema->patternProperties);
        $data['dependentSchemas'] = $this->schemaMapToArray($schema->dependentSchemas);
        $data['enum'] = $schema->enum;

        return $data;
    }

    private function schemaToArrayOrNull(?Schema $schema): ?array
    {
        if (null === $schema) {
            return null;
        }

        return $this->schemaToArray($schema);
    }

    private function additionalPropertiesToArray(Schema|bool|null $value): array|bool|null
    {
        if ($value instanceof Schema) {
            return $this->schemaToArray($value);
        }

        return $value;
    }

    private function discriminatorToArray(?Discriminator $discriminator): ?array
    {
        if (null === $discriminator) {
            return null;
        }

        return [
            'propertyName' => $discriminator->propertyName,
            'mapping' => $discriminator->mapping,
            'defaultMapping' => $discriminator->defaultMapping,
        ];
    }

    private function xmlToArray(?Xml $xml): ?array
    {
        if (null === $xml) {
            return null;
        }

        return [
            'name' => $xml->name,
            'namespace' => $xml->namespace,
            'prefix' => $xml->prefix,
            'attribute' => $xml->attribute,
            'wrapped' => $xml->wrapped,
            'nodeType' => $xml->nodeType,
        ];
    }

    /**
     * @param list<Schema>|null $schemas
     *
     * @return list<array>|null
     */
    private function schemaListToArray(?array $schemas): ?array
    {
        if (null === $schemas) {
            return null;
        }

        return array_map(fn(Schema $s): array => $this->schemaToArray($s), $schemas);
    }

    /**
     * @param array<string, Schema>|null $schemas
     *
     * @return array<string, array>|null
     */
    private function schemaMapToArray(?array $schemas): ?array
    {
        if (null === $schemas) {
            return null;
        }

        $result = [];

        foreach ($schemas as $key => $schema) {
            $result[$key] = $this->schemaToArray($schema);
        }

        return $result;
    }
}
