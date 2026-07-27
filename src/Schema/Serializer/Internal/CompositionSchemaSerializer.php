<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;

use function array_map;
use function in_array;

/** @internal */
final readonly class CompositionSchemaSerializer
{
    private const array SCHEMA_BOOL_FIELDS = [
        'if',
        'then',
        'else',
        'not',
    ];

    private const array SCHEMA_LIST_FIELDS = ['allOf', 'anyOf', 'oneOf'];

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
            'allOf' => $schema->allOf,
            'anyOf' => $schema->anyOf,
            'oneOf' => $schema->oneOf,
            'not' => $schema->not,
            'if' => $schema->if,
            'then' => $schema->then,
            'else' => $schema->else,
            default => null,
        };
    }

    private function extractSchemaBoolValue(Schema $schema, string $name): Schema|bool|null
    {
        return match ($name) {
            'not' => $schema->not,
            'if' => $schema->if,
            'then' => $schema->then,
            'else' => $schema->else,
            default => null,
        };
    }

    /** @return ?list<Schema> */
    private function extractListValue(Schema $schema, string $name): ?array
    {
        return match ($name) {
            'allOf' => $schema->allOf,
            'anyOf' => $schema->anyOf,
            'oneOf' => $schema->oneOf,
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
