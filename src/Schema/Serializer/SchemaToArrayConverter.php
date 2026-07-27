<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaFieldMetadata;
use Duyler\OpenApi\Schema\Serializer\Internal\ArraySchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\CompositionSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\ObjectSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\ScalarSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\SnapshotContext;

use Closure;
use WeakMap;

use function count;
use function in_array;

final readonly class SchemaToArrayConverter
{
    private const string CIRCULAR_REF_KEY = '__circular_ref__';

    private const array REF_ONLY_FIELDS = ['ref', 'refSummary', 'refDescription'];

    public function __construct(
        private ScalarSchemaSerializer $scalarSerializer = new ScalarSchemaSerializer(),
        private ArraySchemaSerializer $arraySerializer = new ArraySchemaSerializer(),
        private ObjectSchemaSerializer $objectSerializer = new ObjectSchemaSerializer(),
        private CompositionSchemaSerializer $compositionSerializer = new CompositionSchemaSerializer(),
    ) {}

    /** @return array<string, mixed> */
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

            $value = $this->extractWireValue($schema, $field->name);

            if (null !== $value) {
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

        $recurse = $this->snapshotRecurse();
        $context = new SnapshotContext($visited, $recurse);
        $data = [];

        foreach (SchemaFieldMetadata::fields() as $field) {
            $data[$field->name] = $this->extractSnapshotValue($schema, $field, $context);
        }

        $data['hasDefault'] = $schema->hasDefault;
        $data['hasConst'] = $schema->hasConst;

        return $data;
    }

    private function extractWireValue(Schema $schema, string $name): mixed
    {
        return match ($this->categoryOf($name)) {
            'scalar' => $this->scalarSerializer->extractWire($schema, $name),
            'array' => $this->arraySerializer->extractWireValue($schema, $name),
            'object' => $this->objectSerializer->extractWireValue($schema, $name),
            'composition' => $this->compositionSerializer->extractWireValue($schema, $name),
            default => null,
        };
    }

    private function extractSnapshotValue(
        Schema $schema,
        FieldMetadata $field,
        SnapshotContext $context,
    ): mixed {
        return match ($field->category) {
            FieldMetadata::CATEGORY_FLAT, FieldMetadata::CATEGORY_STRING, FieldMetadata::CATEGORY_NUMERIC
                => $this->scalarSerializer->extractSnapshot($schema, $field),
            FieldMetadata::CATEGORY_ARRAY
                => $this->arraySerializer->extractSnapshot($schema, $field, $context),
            FieldMetadata::CATEGORY_OBJECT
                => $this->objectSerializer->extractSnapshot($schema, $field, $context),
            FieldMetadata::CATEGORY_COMPOSITION
                => $this->compositionSerializer->extractSnapshot($schema, $field, $context),
            default => null,
        };
    }

    private function categoryOf(string $fieldName): string
    {
        return match ($fieldName) {
            'maxItems', 'minItems', 'uniqueItems', 'minContains', 'maxContains',
            'items', 'contains', 'prefixItems', 'unevaluatedItems' => 'array',
            'maxProperties', 'minProperties', 'required',
            'properties', 'additionalProperties', 'unevaluatedProperties',
            'patternProperties', 'propertyNames', 'dependentSchemas', 'contentSchema' => 'object',
            'allOf', 'anyOf', 'oneOf', 'not', 'if', 'then', 'else' => 'composition',
            default => 'scalar',
        };
    }

    /** @return Closure(Schema, WeakMap<Schema, int>): array */
    private function snapshotRecurse(): Closure
    {
        $converter = $this;

        return static fn(Schema $schema, WeakMap $visited): array => $converter->toSnapshotArray($schema, $visited);
    }
}
