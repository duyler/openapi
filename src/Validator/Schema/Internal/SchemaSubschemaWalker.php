<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Generator;
use Override;

use function array_filter;
use function array_values;
use function sprintf;

/** @internal */
final readonly class SchemaSubschemaWalker implements SchemaSubschemaWalkerInterface
{
    /** @return Generator<string, Schema, void, void> */
    #[Override]
    public function iterateSubSchemas(Schema $schema): Generator
    {
        foreach ($schema->properties ?? [] as $name => $subSchema) {
            yield 'properties/' . JsonPointer::escapeToken($name) => $subSchema;
        }

        foreach ($schema->prefixItems ?? [] as $index => $subSchema) {
            yield sprintf('prefixItems/%d', $index) => $subSchema;
        }

        foreach ($schema->allOf ?? [] as $index => $subSchema) {
            yield sprintf('allOf/%d', $index) => $subSchema;
        }

        foreach ($schema->anyOf ?? [] as $index => $subSchema) {
            yield sprintf('anyOf/%d', $index) => $subSchema;
        }

        foreach ($schema->oneOf ?? [] as $index => $subSchema) {
            yield sprintf('oneOf/%d', $index) => $subSchema;
        }

        foreach ($schema->patternProperties ?? [] as $pattern => $subSchema) {
            yield 'patternProperties/' . JsonPointer::escapeToken($pattern) => $subSchema;
        }

        foreach ($schema->dependentSchemas ?? [] as $name => $subSchema) {
            yield 'dependentSchemas/' . JsonPointer::escapeToken($name) => $subSchema;
        }

        foreach ($schema->defs ?? [] as $name => $subSchema) {
            yield 'defs/' . JsonPointer::escapeToken($name) => $subSchema;
        }

        yield from $this->singleKeywordSubSchemas($schema);
    }

    /** @return list<Schema> */
    #[Override]
    public function collectSingleSubSchemas(Schema $schema): array
    {
        /** @var list<Schema|bool|null> $candidates */
        $candidates = [
            $schema->items,
            $schema->not,
            $schema->contains,
            $schema->propertyNames,
            $schema->if,
            $schema->then,
            $schema->else,
            $schema->unevaluatedItems,
            $schema->additionalProperties instanceof Schema ? $schema->additionalProperties : null,
            $schema->unevaluatedProperties instanceof Schema ? $schema->unevaluatedProperties : null,
            $schema->contentSchema instanceof Schema ? $schema->contentSchema : null,
        ];

        return array_values(array_filter(
            $candidates,
            static fn(mixed $candidate): bool => $candidate instanceof Schema,
        ));
    }

    /** @return Generator<string, Schema, void, void> */
    private function singleKeywordSubSchemas(Schema $schema): Generator
    {
        if ($schema->items instanceof Schema) {
            yield 'items' => $schema->items;
        }

        if ($schema->not instanceof Schema) {
            yield 'not' => $schema->not;
        }

        if ($schema->contains instanceof Schema) {
            yield 'contains' => $schema->contains;
        }

        if ($schema->propertyNames instanceof Schema) {
            yield 'propertyNames' => $schema->propertyNames;
        }

        if ($schema->if instanceof Schema) {
            yield 'if' => $schema->if;
        }

        if ($schema->then instanceof Schema) {
            yield 'then' => $schema->then;
        }

        if ($schema->else instanceof Schema) {
            yield 'else' => $schema->else;
        }

        if ($schema->unevaluatedItems instanceof Schema) {
            yield 'unevaluatedItems' => $schema->unevaluatedItems;
        }

        if ($schema->additionalProperties instanceof Schema) {
            yield 'additionalProperties' => $schema->additionalProperties;
        }

        if ($schema->unevaluatedProperties instanceof Schema) {
            yield 'unevaluatedProperties' => $schema->unevaluatedProperties;
        }

        if ($schema->contentSchema instanceof Schema) {
            yield 'contentSchema' => $schema->contentSchema;
        }
    }
}
