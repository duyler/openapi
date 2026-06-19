<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\CompilationCacheException;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Override;
use Psr\Cache\CacheItemPoolInterface;

use WeakMap;

use JsonException;

use function is_string;
use function json_encode;
use function spl_object_id;

use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class CompilationCache implements CompilationCacheInterface
{
    private const int DEFAULT_CACHE_TTL = 86400;

    private const string CIRCULAR_REF_KEY = '__circular_ref__';

    /** @var WeakMap<Schema, string> */
    private WeakMap $hashCache;

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly string $namespace = 'validator_compilation',
    ) {
        /** @var WeakMap<Schema, string> */
        $this->hashCache = new WeakMap();
    }

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
        if ($this->hashCache->offsetExists($schema)) {
            /** @var string */
            return $this->hashCache[$schema];
        }

        $data = $this->schemaToArray($schema, []);

        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CompilationCacheException(
                sprintf('Failed to encode schema for hash: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $hash = hash('sha256', $json);
        $this->hashCache[$schema] = $hash;

        return $hash;
    }

    /**
     * @param array<int, true> $visited
     */
    private function schemaToArray(Schema $schema, array $visited): array
    {
        $id = spl_object_id($schema);

        if (isset($visited[$id])) {
            return [self::CIRCULAR_REF_KEY => $id];
        }

        $visited[$id] = true;

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
            'propertyNames' => $this->schemaToArrayOrNull($schema->propertyNames, $visited),
            'unevaluatedProperties' => $this->additionalPropertiesToArray($schema->unevaluatedProperties, $visited),
            'unevaluatedItems' => $this->schemaToArrayOrNull($schema->unevaluatedItems, $visited),
            'contains' => $this->schemaToArrayOrNull($schema->contains, $visited),
            'minContains' => $schema->minContains,
            'maxContains' => $schema->maxContains,
            'if' => $this->schemaToArrayOrNull($schema->if, $visited),
            'then' => $this->schemaToArrayOrNull($schema->then, $visited),
            'else' => $this->schemaToArrayOrNull($schema->else, $visited),
            'example' => $schema->example,
            'examples' => $schema->examples,
            'contentEncoding' => $schema->contentEncoding,
            'contentMediaType' => $schema->contentMediaType,
            'contentSchema' => $this->additionalPropertiesToArray($schema->contentSchema, $visited),
            'jsonSchemaDialect' => $schema->jsonSchemaDialect,
            'xml' => $this->xmlToArray($schema->xml),
        ];

        $data['allOf'] = $this->schemaListToArray($schema->allOf, $visited);
        $data['anyOf'] = $this->schemaListToArray($schema->anyOf, $visited);
        $data['oneOf'] = $this->schemaListToArray($schema->oneOf, $visited);
        $data['not'] = $this->schemaToArrayOrNull($schema->not, $visited);
        $data['properties'] = $this->schemaMapToArray($schema->properties, $visited);
        $data['additionalProperties'] = $this->additionalPropertiesToArray($schema->additionalProperties, $visited);
        $data['items'] = $this->schemaToArrayOrNull($schema->items, $visited);
        $data['prefixItems'] = $this->schemaListToArray($schema->prefixItems, $visited);
        $data['patternProperties'] = $this->schemaMapToArray($schema->patternProperties, $visited);
        $data['dependentSchemas'] = $this->schemaMapToArray($schema->dependentSchemas, $visited);
        $data['enum'] = $schema->enum;

        return $data;
    }

    /**
     * @param array<int, true> $visited
     */
    private function schemaToArrayOrNull(?Schema $schema, array $visited): ?array
    {
        if (null === $schema) {
            return null;
        }

        return $this->schemaToArray($schema, $visited);
    }

    /**
     * @param array<int, true> $visited
     */
    private function additionalPropertiesToArray(Schema|bool|null $value, array $visited): array|bool|null
    {
        if ($value instanceof Schema) {
            return $this->schemaToArray($value, $visited);
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
     * @param array<int, true> $visited
     *
     * @return list<array>|null
     */
    private function schemaListToArray(?array $schemas, array $visited): ?array
    {
        if (null === $schemas) {
            return null;
        }

        return array_map(fn(Schema $s): array => $this->schemaToArray($s, $visited), $schemas);
    }

    /**
     * @param array<string, Schema>|null $schemas
     * @param array<int, true> $visited
     *
     * @return array<string, array>|null
     */
    private function schemaMapToArray(?array $schemas, array $visited): ?array
    {
        if (null === $schemas) {
            return null;
        }

        $result = [];

        foreach ($schemas as $key => $schema) {
            $result[$key] = $this->schemaToArray($schema, $visited);
        }

        return $result;
    }
}
