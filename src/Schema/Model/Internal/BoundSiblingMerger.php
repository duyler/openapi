<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaSiblingMerger;

use function array_merge;
use function array_slice;
use function count;
use function is_bool;
use function max;
use function min;

/**
 * @internal
 */
final readonly class BoundSiblingMerger implements SiblingMergerStrategy
{
    /**
     * @return array{
     *     maximum: ?float,
     *     exclusiveMaximum: ?float,
     *     minimum: ?float,
     *     exclusiveMinimum: ?float,
     *     maxLength: ?int,
     *     minLength: ?int,
     *     maxItems: ?int,
     *     minItems: ?int,
     *     maxProperties: ?int,
     *     minProperties: ?int,
     *     uniqueItems: ?bool,
     *     not: Schema|bool|null,
     *     items: Schema|bool|null,
     *     additionalProperties: Schema|bool|null,
     *     unevaluatedProperties: Schema|bool|null,
     *     contains: Schema|bool|null,
     *     propertyNames: Schema|bool|null,
     *     unevaluatedItems: Schema|bool|null,
     *     contentSchema: Schema|bool|null,
     *     prefixItems: ?list<Schema>,
     * }
     */
    public function merge(SiblingMergeContext $context): array
    {
        $resolved = $context->resolved;
        $sibling = $context->sibling;

        return [
            'maximum' => $this->mergeUpperBound($resolved->maximum, $sibling->maximum),
            'exclusiveMaximum' => $this->mergeUpperBound($resolved->exclusiveMaximum, $sibling->exclusiveMaximum),
            'minimum' => $this->mergeLowerBound($resolved->minimum, $sibling->minimum),
            'exclusiveMinimum' => $this->mergeLowerBound($resolved->exclusiveMinimum, $sibling->exclusiveMinimum),
            'maxLength' => $this->mergeUpperBound($resolved->maxLength, $sibling->maxLength),
            'minLength' => $this->mergeLowerBound($resolved->minLength, $sibling->minLength),
            'maxItems' => $this->mergeUpperBound($resolved->maxItems, $sibling->maxItems),
            'minItems' => $this->mergeLowerBound($resolved->minItems, $sibling->minItems),
            'maxProperties' => $this->mergeUpperBound($resolved->maxProperties, $sibling->maxProperties),
            'minProperties' => $this->mergeLowerBound($resolved->minProperties, $sibling->minProperties),
            'uniqueItems' => $sibling->uniqueItems ?? $resolved->uniqueItems,
            'not' => $this->mergeSchemaOrBool($resolved->not, $sibling->not),
            'items' => $this->mergeSchemaOrBool($resolved->items, $sibling->items),
            'additionalProperties' => $this->mergeSchemaOrBool($resolved->additionalProperties, $sibling->additionalProperties),
            'unevaluatedProperties' => $this->mergeSchemaOrBool($resolved->unevaluatedProperties, $sibling->unevaluatedProperties),
            'contains' => $this->mergeSchemaOrBool($resolved->contains, $sibling->contains),
            'propertyNames' => $this->mergeSchemaOrBool($resolved->propertyNames, $sibling->propertyNames),
            'unevaluatedItems' => $this->mergeSchemaOrBool($resolved->unevaluatedItems, $sibling->unevaluatedItems),
            'contentSchema' => $this->mergeSchemaOrBool($resolved->contentSchema, $sibling->contentSchema),
            'prefixItems' => $this->mergePrefixItems($resolved->prefixItems, $sibling->prefixItems),
        ];
    }

    /**
     * @template T of int|float
     *
     * @param T|null $resolved
     * @param T|null $sibling
     *
     * @return T|null
     */
    private function mergeLowerBound(int|float|null $resolved, int|float|null $sibling): int|float|null
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        return max($resolved, $sibling);
    }

    /**
     * @template T of int|float
     *
     * @param T|null $resolved
     * @param T|null $sibling
     *
     * @return T|null
     */
    private function mergeUpperBound(int|float|null $resolved, int|float|null $sibling): int|float|null
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        return min($resolved, $sibling);
    }

    /**
     * @param Schema|bool|null $resolved
     * @param Schema|bool|null $sibling
     */
    private function mergeSchemaOrBool(Schema|bool|null $resolved, Schema|bool|null $sibling): Schema|bool|null
    {
        if (false === $sibling || false === $resolved) {
            return false;
        }

        // §3 fix: original yoda-style `true === X` violated §3 rule.
        // After false === check above, is_bool narrows to `true` only — redundant `&& X` removed (Psalm hint).
        if (is_bool($sibling)) {
            return $resolved;
        }

        if (is_bool($resolved)) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return new SchemaSiblingMerger()->merge($resolved, $sibling);
    }

    /**
     * @param ?list<Schema> $resolved
     * @param ?list<Schema> $sibling
     *
     * @return ?list<Schema>
     */
    private function mergePrefixItems(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        $resolvedCount = count($resolved);
        $siblingCount = count($sibling);
        $overlap = min($resolvedCount, $siblingCount);

        $merger = new SchemaSiblingMerger();
        $merged = [];
        for ($i = 0; $i < $overlap; ++$i) {
            $merged[] = $merger->merge($resolved[$i], $sibling[$i]);
        }

        if ($resolvedCount > $overlap) {
            return array_merge($merged, array_slice($resolved, $overlap));
        }

        if ($siblingCount > $overlap) {
            return array_merge($merged, array_slice($sibling, $overlap));
        }

        return $merged;
    }
}
