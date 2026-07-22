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
use function is_array;

/**
 * Merges a resolved schema with its sibling schema per JSON Schema
 * 2020-12 §8.2.3: when $ref is present, sibling keywords are evaluated
 * alongside the referenced schema (not ignored as in draft-7), treating
 * the combined result as an `allOf` of the referenced schema and the
 * sibling schema.
 *
 * Merge strategy:
 * - type: intersection of type sets. Each side may be a string or a
 *   list (JSON Schema 2020-12 nullable form). Disjoint intersection
 *   (e.g. `integer` + `string`) returns null and both sides are wrapped
 *   into separate `allOf` sub-schemas so the validator rejects every
 *   value per ALL OF semantics (R3-SPEC-006).
 * - multipleOf / pattern: when both sides declare the keyword, both are
 *   wrapped into separate `allOf` sub-schemas. Numeric LCM and regex
 *   conjunction are intentionally not computed inline because (a) LCM is
 *   undefined for non-integer multiples and (b) regex lookaheads risk
 *   catastrophic backtracking and downstream-consumer incompatibility.
 * - format: identical formats collapse to one; divergent formats wrap
 *   into separate `allOf` sub-schemas (R3-SPEC-006).
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
 * - if / then / else: when both sides declare any of the triple, each
 *   side's triple is wrapped into a separate `allOf` sub-schema so the
 *   two conditional applicators apply independently (R3-SPEC-006). When
 *   only one side declares the triple, that value is inherited.
 * - Schema|bool|null unions (additionalProperties / unevaluatedProperties /
 *   contentSchema / not / items / contains / propertyNames /
 *   unevaluatedItems): `false` wins (stricter); `true` is a no-op;
 *   `null` inherits the other side; two Schema instances are merged
 *   recursively via {@see merge()} per R3-SPEC-019. Cycle detection is
 *   delegated to the caller's reference-resolution loop (the merger is
 *   only invoked once per `$ref + sibling` resolution, so a visited-set
 *   is unnecessary here).
 * - properties / patternProperties / dependentSchemas / examples: shallow
 *   per-key merge — sibling entry wins on key collision, deep per-property
 *   merge is a future enhancement.
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

        $scalarAdditions = $this->collectScalarFieldAdditions($resolved, $sibling);
        $compositionAdditions = $this->collectCompositionFieldAdditions($resolved, $sibling);

        foreach ([$scalarAdditions, $compositionAdditions] as $additions) {
            if ([] !== $additions) {
                $allOf = array_merge($allOf ?? [], $additions);
            }
        }

        [$mergedIf, $mergedThen, $mergedElse, $ifThenElseAdditions] = $this->mergeIfThenElse($resolved, $sibling);
        if ([] !== $ifThenElseAdditions) {
            $allOf = array_merge($allOf ?? [], $ifThenElseAdditions);
        }

        return new Schema(
            ref: null,
            refSummary: null,
            refDescription: null,
            format: $this->mergeFormat($resolved->format, $sibling->format),
            title: $sibling->refSummary ?? $sibling->title ?? $resolved->title,
            description: $sibling->refDescription ?? $sibling->description ?? $resolved->description,
            default: $sibling->hasDefault ? $sibling->default : $resolved->default,
            hasDefault: $sibling->hasDefault || $resolved->hasDefault,
            deprecated: $sibling->deprecated || $resolved->deprecated,
            readOnly: $sibling->readOnly || $resolved->readOnly,
            writeOnly: $sibling->writeOnly || $resolved->writeOnly,
            type: $this->mergeType($resolved->type, $sibling->type),
            nullable: $sibling->nullable && $resolved->nullable,
            const: $sibling->hasConst ? $sibling->const : $resolved->const,
            hasConst: $sibling->hasConst || $resolved->hasConst,
            multipleOf: $this->mergeNullableIdentical($resolved->multipleOf, $sibling->multipleOf),
            maximum: $this->mergeUpperBound($resolved->maximum, $sibling->maximum),
            exclusiveMaximum: $this->mergeUpperBound($resolved->exclusiveMaximum, $sibling->exclusiveMaximum),
            minimum: $this->mergeLowerBound($resolved->minimum, $sibling->minimum),
            exclusiveMinimum: $this->mergeLowerBound($resolved->exclusiveMinimum, $sibling->exclusiveMinimum),
            maxLength: $this->mergeUpperBound($resolved->maxLength, $sibling->maxLength),
            minLength: $this->mergeLowerBound($resolved->minLength, $sibling->minLength),
            pattern: $this->mergeNullableIdentical($resolved->pattern, $sibling->pattern),
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
            if: $mergedIf,
            then: $mergedThen,
            else: $mergedElse,
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
     * JSON Schema 2020-12 §8.2.3 ALL OF semantics for `Schema|bool|null`
     * fields. Boolean schemas follow §4.3.2: `false` is the stricter
     * constraint and wins over any other input; `true` is a no-op that
     * inherits the other side. Two Schema instances are merged recursively
     * via {@see merge()} so the referenced schema's constraints are not
     * silently erased by the sibling (R3-SPEC-019).
     *
     * @param Schema|bool|null $resolved
     * @param Schema|bool|null $sibling
     */
    private function mergeSchemaOrBool(Schema|bool|null $resolved, Schema|bool|null $sibling): Schema|bool|null
    {
        if (false === $sibling || false === $resolved) {
            return false;
        }

        if (true === $sibling) {
            return $resolved;
        }

        if (true === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return $this->merge($resolved, $sibling);
    }

    /**
     * Returns null when both sides are non-null so the caller can collect
     * the value into `allOf`. Used for scalar fields whose ALL OF
     * semantics cannot be combined inline (`multipleOf` and `pattern`).
     *
     * @template T
     *
     * @param T|null $resolved
     * @param T|null $sibling
     *
     * @return T|null
     */
    private function mergeNullableIdentical(mixed $resolved, mixed $sibling): mixed
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        return null;
    }

    /**
     * Wraps divergent scalar fields (`multipleOf`, `pattern`) into allOf
     * sub-schemas so both constraints apply per JSON Schema 2020-12 §8.2.3.
     *
     * @return list<Schema>
     */
    private function collectScalarFieldAdditions(Schema $resolved, Schema $sibling): array
    {
        $additions = [];

        if (null !== $resolved->multipleOf && null !== $sibling->multipleOf) {
            $additions[] = new Schema(multipleOf: $resolved->multipleOf);
            $additions[] = new Schema(multipleOf: $sibling->multipleOf);
        }

        if (null !== $resolved->pattern && null !== $sibling->pattern) {
            $additions[] = new Schema(pattern: $resolved->pattern);
            $additions[] = new Schema(pattern: $sibling->pattern);
        }

        return $additions;
    }

    /**
     * Wraps divergent `format` declarations and disjoint `type`
     * declarations into allOf sub-schemas. Identical formats collapse to
     * a single value via {@see mergeFormat}; overlapping types intersect
     * via {@see mergeType}.
     *
     * @return list<Schema>
     */
    private function collectCompositionFieldAdditions(Schema $resolved, Schema $sibling): array
    {
        $additions = [];

        if (null !== $resolved->type && null !== $sibling->type && null === $this->mergeType($resolved->type, $sibling->type)) {
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
     * Merges two format declarations: identical formats collapse to one;
     * divergent formats wrap into separate allOf sub-schemas via the
     * caller.
     */
    private function mergeFormat(?string $resolved, ?string $sibling): ?string
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        return $resolved === $sibling ? $resolved : null;
    }

    /**
     * Intersects two `type` declarations. Both may be a single string or
     * a list (JSON Schema 2020-12 nullable form). Returns the
     * intersection: empty intersection returns null (caller wraps both
     * into separate allOf sub-schemas); single element returns the string
     * form; multiple elements return the list form.
     *
     * @param string|list<string>|null $resolved
     * @param string|list<string>|null $sibling
     *
     * @return string|list<string>|null
     */
    private function mergeType(string|array|null $resolved, string|array|null $sibling): string|array|null
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        $resolvedSet = is_array($resolved) ? $resolved : [$resolved];
        $siblingSet = is_array($sibling) ? $sibling : [$sibling];
        $intersection = array_values(array_intersect($resolvedSet, $siblingSet));

        if ([] === $intersection) {
            return null;
        }

        return 1 === count($intersection) ? $intersection[0] : $intersection;
    }

    /**
     * Merges the `if` / `then` / `else` triple. Each keyword is an in-place
     * applicator that applies independently per JSON Schema 2020-12
     * §10.2.2. When both sides declare any element of the triple, both
     * sides' triples are wrapped into separate allOf sub-schemas so each
     * conditional applicator survives. When only one side declares the
     * triple, that value is inherited.
     *
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
