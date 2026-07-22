<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaFieldMetadata;
use Duyler\OpenApi\Schema\Model\Xml;

use WeakMap;
use Duyler\OpenApi\Compiler\CompilationCache;

use function count;
use function in_array;

/**
 * Serialises a {@see Schema} into an array form.
 *
 * Two modes are provided because callers need two different shapes:
 *
 *  - {@see toWireArray()} emits the OpenAPI 3.2 wire format: null fields are
 *    omitted, `$ref` short-circuits the body, `deprecated`/`readOnly`/
 *    `writeOnly`/`nullable` are emitted only when truthy, and `default`/`const`
 *    respect their `hasDefault`/`hasConst` sentinels. This is the form used by
 *    {@see Schema::jsonSerialize()}.
 *
 *  - {@see toSnapshotArray()} emits a complete, null-preserving snapshot used
 *    by {@see CompilationCache} for cache-key hashing.
 *    Nested Schema / Discriminator / Xml objects are recursively flattened and
 *    a WeakMap tracks cycles so recursive schemas hash deterministically.
 *
 * The field iteration list is sourced from {@see SchemaFieldMetadata}, so a
 * new field requires editing only the catalog plus one match arm in
 * {@see extractWireValue()} / {@see extractSnapshotValue()}.
 */
final readonly class SchemaToArrayConverter
{
    private const string CIRCULAR_REF_KEY = '__circular_ref__';

    private const array REF_ONLY_FIELDS = ['ref', 'refSummary', 'refDescription'];

    private const array SCHEMA_BOOL_FIELDS = [
        'unevaluatedProperties',
        'additionalProperties',
        'contentSchema',
        'propertyNames',
        'unevaluatedItems',
        'contains',
        'if',
        'then',
        'else',
        'not',
        'items',
    ];

    private const array SCHEMA_LIST_FIELDS = ['allOf', 'anyOf', 'oneOf', 'prefixItems'];

    private const array SCHEMA_MAP_FIELDS = ['properties', 'patternProperties', 'dependentSchemas'];

    /**
     * @return array<string, mixed>
     */
    public function toWireArray(Schema $schema): array
    {
        if (null !== $schema->ref) {
            $data = ['$ref' => $schema->ref];

            if (null !== $schema->refSummary) {
                $data['summary'] = $schema->refSummary;
            }

            if (null !== $schema->refDescription) {
                $data['description'] = $schema->refDescription;
            }

            return $data;
        }

        /** @var array<string, mixed> $data */
        $data = [];

        foreach (SchemaFieldMetadata::fields() as $field) {
            if (in_array($field->name, self::REF_ONLY_FIELDS, true)) {
                continue;
            }

            if ('default' === $field->name) {
                if ($schema->hasDefault) {
                    $data[$field->openApiName] = $schema->default;
                }
                continue;
            }

            if ('const' === $field->name) {
                if ($schema->hasConst) {
                    $data[$field->openApiName] = $schema->const;
                }
                continue;
            }

            /** @var mixed $value */
            $value = $this->extractWireValue($schema, $field->name);

            if (null !== $value) {
                /** @var mixed $data[$field->openApiName] */
                $data[$field->openApiName] = $value;
            }
        }

        return $data;
    }

    /**
     * @param WeakMap<Schema, int> $visited
     *
     * @return array<string, mixed>
     */
    public function toSnapshotArray(Schema $schema, WeakMap $visited): array
    {
        if ($visited->offsetExists($schema)) {
            /** @var int */
            $id = $visited[$schema];

            return [self::CIRCULAR_REF_KEY => $id];
        }

        $id = count($visited);
        $visited[$schema] = $id;

        $data = [];

        foreach (SchemaFieldMetadata::fields() as $field) {
            /** @var mixed $data[$field->name] */
            $data[$field->name] = $this->extractSnapshotValue($schema, $field, $visited);
        }

        $data['hasDefault'] = $schema->hasDefault;
        $data['hasConst'] = $schema->hasConst;

        return $data;
    }

    private function extractWireValue(Schema $schema, string $name): mixed
    {
        return match ($name) {
            'title' => $schema->title,
            'description' => $schema->description,
            'default' => $schema->hasDefault ? $schema->default : null,
            'deprecated' => $schema->deprecated ?: null,
            'readOnly' => $schema->readOnly ?: null,
            'writeOnly' => $schema->writeOnly ?: null,
            'type' => $schema->type,
            'nullable' => $schema->nullable ?: null,
            'const' => $schema->hasConst ? $schema->const : null,
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
            'allOf' => $schema->allOf,
            'anyOf' => $schema->anyOf,
            'oneOf' => $schema->oneOf,
            'not' => $schema->not,
            'discriminator' => $schema->discriminator,
            'properties' => $schema->properties,
            'additionalProperties' => $schema->additionalProperties,
            'unevaluatedProperties' => $schema->unevaluatedProperties,
            'items' => $schema->items,
            'prefixItems' => $schema->prefixItems,
            'contains' => $schema->contains,
            'minContains' => $schema->minContains,
            'maxContains' => $schema->maxContains,
            'patternProperties' => $schema->patternProperties,
            'propertyNames' => $schema->propertyNames,
            'dependentSchemas' => $schema->dependentSchemas,
            'if' => $schema->if,
            'then' => $schema->then,
            'else' => $schema->else,
            'unevaluatedItems' => $schema->unevaluatedItems,
            'example' => $schema->example,
            'examples' => $schema->examples,
            'enum' => $schema->enum,
            'format' => $schema->format,
            'contentEncoding' => $schema->contentEncoding,
            'contentMediaType' => $schema->contentMediaType,
            'contentSchema' => $schema->contentSchema,
            'jsonSchemaDialect' => $schema->jsonSchemaDialect,
            'xml' => $schema->xml,
            default => null,
        };
    }

    /**
     * @param WeakMap<Schema, int> $visited
     */
    private function extractSnapshotValue(Schema $schema, FieldMetadata $field, WeakMap $visited): mixed
    {
        $name = $field->name;

        if ('discriminator' === $name) {
            return $this->discriminatorToArray($schema->discriminator);
        }

        if ('xml' === $name) {
            return $this->xmlToArray($schema->xml);
        }

        if ('default' === $name) {
            return $schema->hasDefault ? $schema->default : null;
        }

        if (in_array($name, self::SCHEMA_BOOL_FIELDS, true)) {
            return $this->schemaOrBoolToArray($this->propertySchemaBool($schema, $name), $visited);
        }

        if (in_array($name, self::SCHEMA_LIST_FIELDS, true)) {
            return $this->schemaListToArray($this->propertyList($schema, $name), $visited);
        }

        if (in_array($name, self::SCHEMA_MAP_FIELDS, true)) {
            return $this->schemaMapToArray($this->propertyMap($schema, $name), $visited);
        }

        return $this->extractWireValue($schema, $name);
    }

    private function propertySchemaBool(Schema $schema, string $name): Schema|bool|null
    {
        return match ($name) {
            'unevaluatedProperties' => $schema->unevaluatedProperties,
            'additionalProperties' => $schema->additionalProperties,
            'contentSchema' => $schema->contentSchema,
            'propertyNames' => $schema->propertyNames,
            'unevaluatedItems' => $schema->unevaluatedItems,
            'contains' => $schema->contains,
            'if' => $schema->if,
            'then' => $schema->then,
            'else' => $schema->else,
            'not' => $schema->not,
            'items' => $schema->items,
            default => null,
        };
    }

    /**
     * @return list<Schema>|null
     */
    private function propertyList(Schema $schema, string $name): ?array
    {
        return match ($name) {
            'allOf' => $schema->allOf,
            'anyOf' => $schema->anyOf,
            'oneOf' => $schema->oneOf,
            'prefixItems' => $schema->prefixItems,
            default => null,
        };
    }

    /**
     * @return array<string, Schema>|null
     */
    private function propertyMap(Schema $schema, string $name): ?array
    {
        return match ($name) {
            'properties' => $schema->properties,
            'patternProperties' => $schema->patternProperties,
            'dependentSchemas' => $schema->dependentSchemas,
            default => null,
        };
    }

    /**
     * @param WeakMap<Schema, int> $visited
     */
    private function schemaOrBoolToArray(Schema|bool|null $value, WeakMap $visited): array|bool|null
    {
        if ($value instanceof Schema) {
            return $this->toSnapshotArray($value, $visited);
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
     * @param list<Schema>|null    $schemas
     * @param WeakMap<Schema, int> $visited
     *
     * @return list<array>|null
     */
    private function schemaListToArray(?array $schemas, WeakMap $visited): ?array
    {
        if (null === $schemas) {
            return null;
        }

        return array_map(fn(Schema $s): array => $this->toSnapshotArray($s, $visited), $schemas);
    }

    /**
     * @param array<string, Schema>|null $schemas
     * @param WeakMap<Schema, int>       $visited
     *
     * @return array<string, array>|null
     */
    private function schemaMapToArray(?array $schemas, WeakMap $visited): ?array
    {
        if (null === $schemas) {
            return null;
        }

        $result = [];

        foreach ($schemas as $key => $schema) {
            $result[$key] = $this->toSnapshotArray($schema, $visited);
        }

        return $result;
    }
}
