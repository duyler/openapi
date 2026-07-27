<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;

use function in_array;

/** @internal */
final readonly class ObjectSchemaSerializer
{
    private const array SCHEMA_BOOL_FIELDS = [
        'additionalProperties',
        'unevaluatedProperties',
        'propertyNames',
        'contentSchema',
    ];

    private const array SCHEMA_MAP_FIELDS = ['properties', 'patternProperties', 'dependentSchemas'];

    public function extractSnapshot(
        Schema $schema,
        FieldMetadata $field,
        SnapshotContext $context,
    ): mixed {
        if (in_array($field->name, self::SCHEMA_BOOL_FIELDS, true)) {
            return $this->schemaOrBoolToArray(
                $this->extractSchemaBoolValue($schema, $field->name),
                $context,
            );
        }

        if (in_array($field->name, self::SCHEMA_MAP_FIELDS, true)) {
            return $this->schemaMapToArray(
                $this->extractMapValue($schema, $field->name),
                $context,
            );
        }

        return $this->extractWireValue($schema, $field->name);
    }

    public function extractWireValue(Schema $schema, string $name): mixed
    {
        return match ($name) {
            'maxProperties' => $schema->maxProperties,
            'minProperties' => $schema->minProperties,
            'required' => $schema->required,
            'properties' => $schema->properties,
            'additionalProperties' => $schema->additionalProperties,
            'unevaluatedProperties' => $schema->unevaluatedProperties,
            'patternProperties' => $schema->patternProperties,
            'propertyNames' => $schema->propertyNames,
            'dependentSchemas' => $schema->dependentSchemas,
            'contentSchema' => $schema->contentSchema,
            default => null,
        };
    }

    private function extractSchemaBoolValue(Schema $schema, string $name): Schema|bool|null
    {
        return match ($name) {
            'additionalProperties' => $schema->additionalProperties,
            'unevaluatedProperties' => $schema->unevaluatedProperties,
            'propertyNames' => $schema->propertyNames,
            'contentSchema' => $schema->contentSchema,
            default => null,
        };
    }

    /** @return array<string, Schema>|null */
    private function extractMapValue(Schema $schema, string $name): ?array
    {
        return match ($name) {
            'properties' => $schema->properties,
            'patternProperties' => $schema->patternProperties,
            'dependentSchemas' => $schema->dependentSchemas,
            default => null,
        };
    }

    private function schemaOrBoolToArray(
        Schema|bool|null $value,
        SnapshotContext $context,
    ): array|bool|null {
        if ($value instanceof Schema) {
            return ($context->recurseSnapshot)($value, $context->visited);
        }

        return $value;
    }

    /**
     * @param array<string, Schema>|null $schemas
     *
     * @return array<string, array>|null
     */
    private function schemaMapToArray(
        ?array $schemas,
        SnapshotContext $context,
    ): ?array {
        if (null === $schemas) {
            return null;
        }

        $recurse = $context->recurseSnapshot;
        $visited = $context->visited;
        $result = [];

        foreach ($schemas as $key => $schema) {
            $result[$key] = $recurse($schema, $visited);
        }

        return $result;
    }
}
