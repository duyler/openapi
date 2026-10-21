<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaFieldMetadata;
use Duyler\OpenApi\Schema\Serializer\Internal\ArraySchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\CompositionSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\ObjectSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\ScalarSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\SnapshotContext;

use Closure;
use WeakMap;

use function count;
use function in_array;
use function ucfirst;

final readonly class SchemaToArrayConverter
{
    private const string CIRCULAR_REF_KEY = '__circular_ref__';

    private const array REF_ONLY_FIELDS = ['ref', 'refSummary', 'refDescription'];

    public function __construct(
        private ScalarSchemaSerializer $scalarSerializer = new ScalarSchemaSerializer(),
        private ArraySchemaSerializer $arraySerializer = new ArraySchemaSerializer(),
        private ObjectSchemaSerializer $objectSerializer = new ObjectSchemaSerializer(),
        private CompositionSchemaSerializer $compositionSerializer = new CompositionSchemaSerializer(),
        private RefSerializer $refSerializer = new RefSerializer(),
    ) {}

    /** @return array<string, mixed> */
    public function toWireArray(Schema $schema): array
    {
        $refData = $this->refSerializer->toWireForm($schema->ref, $schema->refSummary, $schema->refDescription);

        if (null !== $refData) {
            return $refData;
        }

        /** @var array<string, mixed> $data */
        $data = [];

        foreach (SchemaFieldMetadata::fields() as $field) {
            if (in_array($field->name, self::REF_ONLY_FIELDS, true)) {
                continue;
            }

            if ($field->hasSentinel) {
                $sentinel = $this->scalarSerializer->extractSentinel($schema, $field);

                if (null !== $sentinel) {
                    $data[$field->openApiName] = $sentinel->value;
                }

                continue;
            }

            $value = $this->extractWireValue($schema, $field);

            if (null !== $value) {
                $data[$field->openApiName] = $value;
            }
        }

        return $data;
    }

    /**
     * @param WeakMap<Schema, int> $visited
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

        foreach (SchemaFieldMetadata::fields() as $field) {
            if (false === $field->hasSentinel) {
                continue;
            }

            $data['has' . ucfirst($field->name)] = null !== $this->scalarSerializer->extractSentinel($schema, $field);
        }

        return $data;
    }

    private function extractWireValue(Schema $schema, FieldMetadata $field): mixed
    {
        return match ($field->category) {
            FieldMetadata::CATEGORY_ARRAY => $this->arraySerializer->extractWireValue($schema, $field->name),
            FieldMetadata::CATEGORY_OBJECT => $this->objectSerializer->extractWireValue($schema, $field->name),
            FieldMetadata::CATEGORY_COMPOSITION => $this->compositionSerializer->extractWireValue($schema, $field->name),
            FieldMetadata::CATEGORY_FLAT, FieldMetadata::CATEGORY_STRING, FieldMetadata::CATEGORY_NUMERIC
                => $this->scalarSerializer->extractWire($schema, $field->name),
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

    /** @return Closure(Schema, WeakMap<Schema, int>): array */
    private function snapshotRecurse(): Closure
    {
        $converter = $this;

        return static fn(Schema $schema, WeakMap $visited): array => $converter->toSnapshotArray($schema, $visited);
    }
}
