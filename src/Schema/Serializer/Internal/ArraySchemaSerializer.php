<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;

use function array_map;
use function in_array;

/** @internal */
final readonly class ArraySchemaSerializer
{
    private const array SCHEMA_BOOL_FIELDS = [
        'items',
        'contains',
        'unevaluatedItems',
    ];

    private const array SCHEMA_LIST_FIELDS = ['prefixItems'];

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

        if (in_array($field->name, self::SCHEMA_LIST_FIELDS, true)) {
            return $this->schemaListToArray(
                $this->extractListValue($schema, $field->name),
                $context,
            );
        }

        return $this->extractWireValue($schema, $field->name);
    }

    public function extractWireValue(Schema $schema, string $name): mixed
    {
        return match ($name) {
            'maxItems' => $schema->maxItems,
            'minItems' => $schema->minItems,
            'uniqueItems' => $schema->uniqueItems,
            'minContains' => $schema->minContains,
            'maxContains' => $schema->maxContains,
            'items' => $schema->items,
            'contains' => $schema->contains,
            'prefixItems' => $schema->prefixItems,
            'unevaluatedItems' => $schema->unevaluatedItems,
            default => null,
        };
    }

    private function extractSchemaBoolValue(Schema $schema, string $name): Schema|bool|null
    {
        return match ($name) {
            'items' => $schema->items,
            'contains' => $schema->contains,
            'unevaluatedItems' => $schema->unevaluatedItems,
            default => null,
        };
    }

    /** @return ?list<Schema> */
    private function extractListValue(Schema $schema, string $name): ?array
    {
        return 'prefixItems' === $name ? $schema->prefixItems : null;
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
     * @param ?list<Schema> $schemas
     *
     * @return ?list<array>
     */
    private function schemaListToArray(
        ?array $schemas,
        SnapshotContext $context,
    ): ?array {
        if (null === $schemas) {
            return null;
        }

        $recurse = $context->recurseSnapshot;
        $visited = $context->visited;

        return array_map(
            static fn(Schema $s): array => $recurse($s, $visited),
            $schemas,
        );
    }
}
