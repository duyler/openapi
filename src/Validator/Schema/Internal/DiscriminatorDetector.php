<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Generator;
use WeakMap;

/** @internal */
final readonly class DiscriminatorDetector
{
    /**
     * @param WeakMap<Schema, true> $visited
     * @param Closure(string, OpenApiDocument): Schema $resolver
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    public function detectDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        WeakMap $visited,
        Closure $resolver,
        int $depth,
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }
        if ($visited->offsetExists($schema)) {
            return [false, $visited];
        }
        $visited[$schema] = true;
        if (null !== $schema->ref) {
            return $this->checkRefForDiscriminator(
                $schema->ref,
                $document,
                $visited,
                $resolver,
                $depth + 1,
            );
        }
        if (null !== $schema->discriminator) {
            return [true, $visited];
        }

        return $this->scanSubSchemasForDiscriminator($schema, $document, $visited, $resolver, $depth);
    }

    /**
     * @param WeakMap<Schema, true> $visited
     * @param Closure(string, OpenApiDocument): Schema $resolver
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    public function checkRefForDiscriminator(
        string $ref,
        OpenApiDocument $document,
        WeakMap $visited,
        Closure $resolver,
        int $depth,
    ): array {
        try {
            $resolvedSchema = $resolver($ref, $document);

            return $this->detectDiscriminator(
                $resolvedSchema,
                $document,
                $visited,
                $resolver,
                $depth,
            );
        } catch (UnresolvableRefException) {
            return [false, $visited];
        }
    }

    /**
     * @param WeakMap<Schema, true> $visited
     *
     * @throws SchemaDepthExceededException
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    public function detectRef(Schema $schema, WeakMap $visited, int $depth): array
    {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if ($visited->offsetExists($schema)) {
            return [false, $visited];
        }

        $visited[$schema] = true;

        if (null !== $schema->ref) {
            return [true, $visited];
        }

        return $this->scanSubSchemasForRef($schema, $visited, $depth);
    }

    /** @return Generator<int, Schema, void, void> */
    public function iterateSubSchemas(Schema $schema): Generator
    {
        yield from $schema->properties ?? [];
        yield from $schema->prefixItems ?? [];
        yield from $schema->allOf ?? [];
        yield from $schema->anyOf ?? [];
        yield from $schema->oneOf ?? [];
        yield from $schema->patternProperties ?? [];
        yield from $schema->dependentSchemas ?? [];

        foreach ($this->collectSingleSubSchemas($schema) as $subSchema) {
            yield $subSchema;
        }
    }

    /** @return list<Schema> */
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
            static fn(mixed $c): bool => $c instanceof Schema,
        ));
    }

    /**
     * @param WeakMap<Schema, true> $visited
     * @param Closure(string, OpenApiDocument): Schema $resolver
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    private function scanSubSchemasForDiscriminator(
        Schema $schema,
        OpenApiDocument $document,
        WeakMap $visited,
        Closure $resolver,
        int $depth,
    ): array {
        foreach ($this->iterateSubSchemas($schema) as $subSchema) {
            [$has, $visited] = $this->detectDiscriminator(
                $subSchema,
                $document,
                $visited,
                $resolver,
                $depth + 1,
            );

            if ($has) {
                return [true, $visited];
            }
        }

        return [false, $visited];
    }

    /**
     * @param WeakMap<Schema, true> $visited
     *
     * @return array{bool, WeakMap<Schema, true>}
     */
    private function scanSubSchemasForRef(Schema $schema, WeakMap $visited, int $depth): array
    {
        foreach ($this->iterateSubSchemas($schema) as $subSchema) {
            [$has, $visited] = $this->detectRef($subSchema, $visited, $depth + 1);

            if ($has) {
                return [true, $visited];
            }
        }

        return [false, $visited];
    }
}
