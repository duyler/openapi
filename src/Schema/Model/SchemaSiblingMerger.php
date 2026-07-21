<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Validator\Schema\JsonEquals;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function array_uintersect;
use function count;
use function max;
use function min;
use function array_slice;

/**
 * Merges a resolved schema with its sibling schema per JSON Schema
 * 2020-12 §8.2.3: when $ref is present, sibling keywords are evaluated
 * alongside the referenced schema (not ignored as in draft-7), treating
 * the combined result as an `allOf` of the referenced schema and the
 * sibling schema.
 *
 * Merge strategy:
 * - Scalars (format/type/pattern/...): sibling wins when set; resolved
 *   value is inherited otherwise.
 * - Scalar bounds (minLength / maxLength / minItems / maxItems /
 *   minProperties / maxProperties / minimum / maximum / exclusiveMinimum /
 *   exclusiveMaximum): stricter-wins — lower bounds use `max`, upper
 *   bounds use `min`. Either side being null inherits the other side.
 * - nullable: logical AND — both sides must permit null for null to be
 *   permitted (constraint that forbids null wins).
 * - Other booleans (deprecated / readOnly / writeOnly / hasDefault /
 *   hasConst): logical OR — a sibling `true` tightens constraints.
 * - default / const: sibling wins when its has* flag is true.
 * - required: array union (both required lists apply).
 * - enum: array intersection via {@see JsonEquals} comparator (both enum
 *   sets must accept the value). Empty intersection is preserved as an
 *   empty list so downstream EnumValidator rejects every value.
 * - allOf: array concatenation (semantically equivalent to ALL OF).
 * - anyOf / oneOf: when only one side declares the keyword, that value is
 *   inherited; when both sides declare it, each side is wrapped into a
 *   single-schema entry and the two wrappers are appended to `allOf` so
 *   the combined result is `(A OR B) AND (C OR D)` instead of the wider
 *   `A OR B OR C OR D` produced by concatenation.
 * - prefixItems: per-index recursive merge — each overlapping index pair
 *   is merged via {@see merge()}, leftover items from the longer side are
 *   appended unchanged.
 * - properties / patternProperties / dependentSchemas / examples: shallow
 *   per-key merge — sibling entry wins on key collision, deep per-property
 *   merge is a future enhancement.
 * - Schema|bool|null unions (additionalProperties / unevaluatedProperties /
 *   contentSchema / not / items / contains / propertyNames / if / then /
 *   else / unevaluatedItems): sibling wins when it is a Schema or an
 *   explicit bool; otherwise the resolved value is kept. Two-Schema merge
 *   (recursive merge or allOf wrapping) is out of scope here and tracked
 *   by R3-SPEC-006; this method only preserves sibling-wins for that case.
 * - title / description: refSummary / refDescription take precedence
 *   (preserves the historical OpenAPI override convention), then the
 *   sibling's own value, then the resolved schema's value.
 *
 * The $ref family is dropped from the merged result because the merger is
 * invoked after reference resolution.
 */
final readonly class SchemaSiblingMerger
{
    public function merge(Schema $resolved, Schema $sibling): Schema
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

        return new Schema(
            ref: null,
            refSummary: null,
            refDescription: null,
            format: $sibling->format ?? $resolved->format,
            title: $sibling->refSummary ?? $sibling->title ?? $resolved->title,
            description: $sibling->refDescription ?? $sibling->description ?? $resolved->description,
            default: $sibling->hasDefault ? $sibling->default : $resolved->default,
            hasDefault: $sibling->hasDefault || $resolved->hasDefault,
            deprecated: $sibling->deprecated || $resolved->deprecated,
            readOnly: $sibling->readOnly || $resolved->readOnly,
            writeOnly: $sibling->writeOnly || $resolved->writeOnly,
            type: $sibling->type ?? $resolved->type,
            nullable: $sibling->nullable && $resolved->nullable,
            const: $sibling->hasConst ? $sibling->const : $resolved->const,
            hasConst: $sibling->hasConst || $resolved->hasConst,
            multipleOf: $sibling->multipleOf ?? $resolved->multipleOf,
            maximum: $this->mergeUpperBound($resolved->maximum, $sibling->maximum),
            exclusiveMaximum: $this->mergeUpperBound($resolved->exclusiveMaximum, $sibling->exclusiveMaximum),
            minimum: $this->mergeLowerBound($resolved->minimum, $sibling->minimum),
            exclusiveMinimum: $this->mergeLowerBound($resolved->exclusiveMinimum, $sibling->exclusiveMinimum),
            maxLength: $this->mergeUpperBound($resolved->maxLength, $sibling->maxLength),
            minLength: $this->mergeLowerBound($resolved->minLength, $sibling->minLength),
            pattern: $sibling->pattern ?? $resolved->pattern,
            maxItems: $this->mergeUpperBound($resolved->maxItems, $sibling->maxItems),
            minItems: $this->mergeLowerBound($resolved->minItems, $sibling->minItems),
            uniqueItems: $sibling->uniqueItems ?? $resolved->uniqueItems,
            maxProperties: $this->mergeUpperBound($resolved->maxProperties, $sibling->maxProperties),
            minProperties: $this->mergeLowerBound($resolved->minProperties, $sibling->minProperties),
            required: $this->mergeStringList($resolved->required, $sibling->required),
            allOf: $allOf,
            anyOf: $this->mergeCompositionField($resolved->anyOf, $sibling->anyOf),
            oneOf: $this->mergeCompositionField($resolved->oneOf, $sibling->oneOf),
            not: $this->mergeSchemaOrBool($resolved->not, $sibling->not),
            discriminator: $sibling->discriminator ?? $resolved->discriminator,
            properties: $this->mergeSchemaMap($resolved->properties, $sibling->properties),
            additionalProperties: $this->mergeSchemaOrBool($resolved->additionalProperties, $sibling->additionalProperties),
            unevaluatedProperties: $this->mergeSchemaOrBool($resolved->unevaluatedProperties, $sibling->unevaluatedProperties),
            items: $this->mergeSchemaOrBool($resolved->items, $sibling->items),
            prefixItems: $this->mergePrefixItems($resolved->prefixItems, $sibling->prefixItems),
            contains: $this->mergeSchemaOrBool($resolved->contains, $sibling->contains),
            minContains: $sibling->minContains ?? $resolved->minContains,
            maxContains: $sibling->maxContains ?? $resolved->maxContains,
            patternProperties: $this->mergeSchemaMap($resolved->patternProperties, $sibling->patternProperties),
            propertyNames: $this->mergeSchemaOrBool($resolved->propertyNames, $sibling->propertyNames),
            dependentSchemas: $this->mergeSchemaMap($resolved->dependentSchemas, $sibling->dependentSchemas),
            if: $this->mergeSchemaOrBool($resolved->if, $sibling->if),
            then: $this->mergeSchemaOrBool($resolved->then, $sibling->then),
            else: $this->mergeSchemaOrBool($resolved->else, $sibling->else),
            unevaluatedItems: $this->mergeSchemaOrBool($resolved->unevaluatedItems, $sibling->unevaluatedItems),
            example: $sibling->example ?? $resolved->example,
            examples: $this->mergeMixedMap($resolved->examples, $sibling->examples),
            enum: $this->mergeEnum($resolved->enum, $sibling->enum),
            contentEncoding: $sibling->contentEncoding ?? $resolved->contentEncoding,
            contentMediaType: $sibling->contentMediaType ?? $resolved->contentMediaType,
            contentSchema: $this->mergeSchemaOrBool($resolved->contentSchema, $sibling->contentSchema),
            jsonSchemaDialect: $sibling->jsonSchemaDialect ?? $resolved->jsonSchemaDialect,
            xml: $sibling->xml ?? $resolved->xml,
        );
    }

    /**
     * @param ?list<string> $resolved
     * @param ?list<string> $sibling
     *
     * @return ?list<string>
     */
    private function mergeStringList(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_values(array_unique(array_merge($resolved, $sibling)));
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

    /**
     * @param ?array<string, mixed> $resolved
     * @param ?array<string, mixed> $sibling
     *
     * @return ?array<string, mixed>
     */
    private function mergeMixedMap(?array $resolved, ?array $sibling): ?array
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
     * Sibling wins when it declares a Schema or an explicit bool; null
     * sibling inherits the resolved schema so absent constraints do not
     * erase referenced ones.
     *
     * @param Schema|bool|null $resolved
     * @param Schema|bool|null $sibling
     */
    private function mergeSchemaOrBool(Schema|bool|null $resolved, Schema|bool|null $sibling): Schema|bool|null
    {
        if (null !== $sibling) {
            return $sibling;
        }

        return $resolved;
    }

    /**
     * JSON Schema 2020-12 §8.2.3: a sibling `enum` and the referenced
     * schema's `enum` both apply, so the data must be in the intersection.
     * Uses {@see JsonEquals} for value comparison so int/float and
     * equivalent structures compare by JSON value, not by identity.
     *
     * Returns null when neither side declares an enum. Returns the
     * non-null side when only one declares. Returns array_values of the
     * intersection otherwise; empty intersection is preserved as [] so
     * EnumValidator rejects every value.
     *
     * @param ?list<mixed> $resolved
     * @param ?list<mixed> $sibling
     *
     * @return ?list<mixed>
     */
    private function mergeEnum(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_values(array_uintersect(
            $resolved,
            $sibling,
            static fn(mixed $a, mixed $b): int => JsonEquals::equals($a, $b)
                ? 0
                : ($a <=> $b),
        ));
    }

    /**
     * ALL OF semantics for a lower scalar bound: the stricter (larger)
     * value wins. Null on either side inherits the other side so an
     * absent constraint cannot relax the resolved one.
     *
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
     * ALL OF semantics for an upper scalar bound: the stricter (smaller)
     * value wins. Null on either side inherits the other side so an
     * absent constraint cannot relax the resolved one.
     *
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
     * Wraps a pair of anyOf/oneOf declarations into a two-element schema
     * list destined for `allOf`, so the combined result reads as
     * `(resolved composition) AND (sibling composition)` instead of the
     * wider disjunction produced by concatenation.
     *
     * Returns null when only one side (or neither) declares the keyword,
     * signalling the caller to keep the original anyOf/oneOf field.
     *
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
     * Reduces the anyOf/oneOf field after {@see wrapCompositionInAllOf}
     * has decided whether the keyword survives on the merged schema.
     * Returns null when both sides declared the keyword (the constraint
     * has been relocated into `allOf`); otherwise inherits the non-null
     * side.
     *
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
     * ALL OF semantics for `prefixItems` (positional tuple validation):
     * each overlapping index pair is merged recursively via {@see merge()};
     * leftover items from the longer side are appended unchanged so the
     * positional order is preserved.
     *
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

        $merged = [];
        for ($i = 0; $i < $overlap; ++$i) {
            $merged[] = $this->merge($resolved[$i], $sibling[$i]);
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
