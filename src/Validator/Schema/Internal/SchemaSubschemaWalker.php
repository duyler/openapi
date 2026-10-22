<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Generator;
use Override;

use function array_filter;
use function array_values;

/** @internal */
final readonly class SchemaSubschemaWalker implements SchemaSubschemaWalkerInterface
{
    /** @return Generator<array-key, Schema, void, void> */
    #[Override]
    public function iterateSubSchemas(Schema $schema): Generator
    {
        yield from $schema->properties ?? [];
        yield from $schema->prefixItems ?? [];
        yield from $schema->allOf ?? [];
        yield from $schema->anyOf ?? [];
        yield from $schema->oneOf ?? [];
        yield from $schema->patternProperties ?? [];
        yield from $schema->dependentSchemas ?? [];
        yield from $schema->defs ?? [];

        foreach ($this->collectSingleSubSchemas($schema) as $subSchema) {
            yield $subSchema;
        }
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
}
