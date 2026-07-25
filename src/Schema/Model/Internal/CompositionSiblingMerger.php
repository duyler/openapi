<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function array_filter;
use function array_merge;

/**
 * @internal
 */
final readonly class CompositionSiblingMerger implements SiblingMergerStrategy
{
    public function __construct(
        private ScalarSiblingMerger $scalarMerger = new ScalarSiblingMerger(),
    ) {}

    /**
     * @return array{
     *     allOf: ?list<Schema>,
     *     anyOf: ?list<Schema>,
     *     oneOf: ?list<Schema>,
     *     if: Schema|bool|null,
     *     then: Schema|bool|null,
     *     else: Schema|bool|null,
     *     properties: ?array<string, Schema>,
     *     patternProperties: ?array<string, Schema>,
     *     dependentSchemas: ?array<string, Schema>,
     * }
     */
    public function merge(SiblingMergeContext $context): array
    {
        $resolved = $context->resolved;
        $sibling = $context->sibling;

        [$mergedIf, $mergedThen, $mergedElse, $ifThenElseAdditions] = $this->mergeIfThenElse($resolved, $sibling);
        $allOf = $this->mergeAllOf($resolved, $sibling, $ifThenElseAdditions);

        return [
            'allOf' => $allOf,
            'anyOf' => $this->mergeCompositionField($resolved->anyOf, $sibling->anyOf),
            'oneOf' => $this->mergeCompositionField($resolved->oneOf, $sibling->oneOf),
            'if' => $mergedIf,
            'then' => $mergedThen,
            'else' => $mergedElse,
            'properties' => $this->mergeSchemaMap($resolved->properties, $sibling->properties),
            'patternProperties' => $this->mergeSchemaMap($resolved->patternProperties, $sibling->patternProperties),
            'dependentSchemas' => $this->mergeSchemaMap($resolved->dependentSchemas, $sibling->dependentSchemas),
        ];
    }

    /**
     * @param list<Schema> $ifThenElseAdditions
     *
     * @return list<Schema>|null
     */
    private function mergeAllOf(Schema $resolved, Schema $sibling, array $ifThenElseAdditions): ?array
    {
        $wrappedAnyOf = $this->wrapCompositionInAllOf($resolved->anyOf, $sibling->anyOf, 'anyOf');
        $wrappedOneOf = $this->wrapCompositionInAllOf($resolved->oneOf, $sibling->oneOf, 'oneOf');

        $compositionsToAdd = array_filter(
            [$wrappedAnyOf, $wrappedOneOf],
            static fn(?array $wrapped): bool => null !== $wrapped,
        );

        $allOf = $this->mergeSchemaList($resolved->allOf, $sibling->allOf);
        if ([] !== $compositionsToAdd) {
            $allOf = array_merge($allOf ?? [], ...$compositionsToAdd);
        }

        $scalarAdditions = $this->scalarMerger->collectScalarFieldAdditions($resolved, $sibling);
        $compositionAdditions = $this->collectCompositionFieldAdditions($resolved, $sibling);

        foreach ([$scalarAdditions, $compositionAdditions] as $additions) {
            if ([] !== $additions) {
                $allOf = array_merge($allOf ?? [], $additions);
            }
        }

        if ([] !== $ifThenElseAdditions) {
            $allOf = array_merge($allOf ?? [], $ifThenElseAdditions);
        }

        return $allOf;
    }

    /**
     * @param ?list<Schema> $resolvedComposition
     * @param ?list<Schema> $siblingComposition
     *
     * @return ?list<Schema>
     */
    private function wrapCompositionInAllOf(
        ?array $resolvedComposition,
        ?array $siblingComposition,
        string $keyword,
    ): ?array {
        if (null === $resolvedComposition || null === $siblingComposition) {
            return null;
        }

        return match ($keyword) {
            'anyOf' => [
                new Schema(anyOf: $resolvedComposition),
                new Schema(anyOf: $siblingComposition),
            ],
            'oneOf' => [
                new Schema(oneOf: $resolvedComposition),
                new Schema(oneOf: $siblingComposition),
            ],
        };
    }

    /**
     * @param ?list<Schema> $resolvedComposition
     * @param ?list<Schema> $siblingComposition
     *
     * @return ?list<Schema>
     */
    private function mergeCompositionField(?array $resolvedComposition, ?array $siblingComposition): ?array
    {
        if (null !== $resolvedComposition && null !== $siblingComposition) {
            return null;
        }

        return $siblingComposition ?? $resolvedComposition;
    }

    /**
     * @return array{0: Schema|bool|null, 1: Schema|bool|null, 2: Schema|bool|null, 3: list<Schema>}
     */
    private function mergeIfThenElse(Schema $resolved, Schema $sibling): array
    {
        $resolvedHas = null !== $resolved->if || null !== $resolved->then || null !== $resolved->else;
        $siblingHas = null !== $sibling->if || null !== $sibling->then || null !== $sibling->else;

        if (false === $resolvedHas || false === $siblingHas) {
            return [
                $sibling->if ?? $resolved->if,
                $sibling->then ?? $resolved->then,
                $sibling->else ?? $resolved->else,
                [],
            ];
        }

        return [
            null,
            null,
            null,
            [
                new Schema(if: $resolved->if, then: $resolved->then, else: $resolved->else),
                new Schema(if: $sibling->if, then: $sibling->then, else: $sibling->else),
            ],
        ];
    }

    /** @return list<Schema> */
    private function collectCompositionFieldAdditions(Schema $resolved, Schema $sibling): array
    {
        $additions = [];

        if (null !== $resolved->type && null !== $sibling->type && null === $this->scalarMerger->mergeType($resolved->type, $sibling->type)) {
            $additions[] = new Schema(type: $resolved->type);
            $additions[] = new Schema(type: $sibling->type);
        }

        if (null !== $resolved->format && null !== $sibling->format && $resolved->format !== $sibling->format) {
            $additions[] = new Schema(format: $resolved->format);
            $additions[] = new Schema(format: $sibling->format);
        }

        return $additions;
    }

    /**
     * @param ?list<Schema> $resolved
     * @param ?list<Schema> $sibling
     *
     * @return ?list<Schema>
     */
    private function mergeSchemaList(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_merge($resolved, $sibling);
    }

    /**
     * @param ?array<string, Schema> $resolved
     * @param ?array<string, Schema> $sibling
     *
     * @return ?array<string, Schema>
     */
    private function mergeSchemaMap(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_merge($resolved, $sibling);
    }
}
