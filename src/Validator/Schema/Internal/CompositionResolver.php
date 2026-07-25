<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use WeakMap;

use function assert;
use function count;

/** @internal */
final class CompositionResolver
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        /** @var WeakMap<Schema, Schema> */
        private WeakMap $resolvedCache,
    ) {}

    /**
     * @param WeakMap<Schema, true> $visited
     *
     * @throws SchemaDepthExceededException
     */
    public function resolveCompositionRefs(Schema $schema, WeakMap $visited): Schema
    {
        if ($this->resolvedCache->offsetExists($schema)) {
            $cached = $this->resolvedCache[$schema];
            assert(null !== $cached);

            return $cached;
        }

        if (ValidationContext::MAX_DEPTH <= count($visited)) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if ($visited->offsetExists($schema)) {
            $this->resolvedCache[$schema] = $schema;

            return $schema;
        }

        $visited[$schema] = true;

        $allOf = $this->resolveCompositionArray($schema->allOf, $visited);

        $hasDiscriminator = null !== $schema->discriminator;

        $anyOf = $hasDiscriminator
            ? $schema->anyOf
            : $this->resolveCompositionArray($schema->anyOf, $visited);

        $oneOf = $hasDiscriminator
            ? $schema->oneOf
            : $this->resolveCompositionArray($schema->oneOf, $visited);

        if ($allOf === $schema->allOf && $anyOf === $schema->anyOf && $oneOf === $schema->oneOf) {
            $this->resolvedCache[$schema] = $schema;

            return $schema;
        }

        $resolved = $schema->withOverrides(
            allOf: $allOf,
            anyOf: $anyOf,
            oneOf: $oneOf,
        );

        $this->resolvedCache[$schema] = $resolved;

        return $resolved;
    }

    /**
     * @param list<Schema>|null      $schemas
     * @param WeakMap<Schema, true>  $visited
     *
     * @return list<Schema>|null
     */
    private function resolveCompositionArray(?array $schemas, WeakMap $visited): ?array
    {
        if (null === $schemas) {
            return null;
        }

        $result = [];
        $changed = false;

        foreach ($schemas as $subSchema) {
            $resolved = $subSchema;

            if (null !== $subSchema->ref) {
                $candidate = $this->dependencies->refResolver->resolveSchemaWithOverride(
                    $subSchema,
                    $this->document,
                );

                if (null === $candidate->discriminator) {
                    $resolved = $candidate;
                    $changed = true;
                }
            }

            $recursivelyResolved = $this->resolveCompositionRefs($resolved, $visited);

            if ($recursivelyResolved !== $resolved) {
                $changed = true;
                $resolved = $recursivelyResolved;
            }

            $result[] = $resolved;
        }

        return $changed ? $result : $schemas;
    }
}
