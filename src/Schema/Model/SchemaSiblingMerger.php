<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Validator\Schema\JsonEquals;

use function array_merge;
use function array_unique;
use function array_values;
use function array_uintersect;

/**
 * Merges a resolved schema with its sibling schema per JSON Schema
 * 2020-12 §8.2.3: when $ref is present, sibling keywords are evaluated
 * alongside the referenced schema (not ignored as in draft-7).
 *
 * Merge strategy:
 * - Scalars (format/type/pattern/numeric and length bounds/...): sibling
 *   wins when set; resolved value is inherited otherwise.
 * - Booleans (deprecated/readOnly/writeOnly/nullable/hasDefault/hasConst):
 *   logical OR — a sibling `true` tightens constraints, `false` cannot
 *   relax the resolved schema.
 * - default / const: sibling wins when its has* flag is true.
 * - required: array union (both required lists apply).
 * - enum: array intersection via {@see JsonEquals} comparator (both enum
 *   sets must accept the value). Empty intersection is preserved as an
 *   empty list so downstream EnumValidator rejects every value.
 * - allOf / anyOf / oneOf / prefixItems: array concatenation.
 * - properties / patternProperties / dependentSchemas / examples: shallow
 *   per-key merge — sibling entry wins on key collision, deep per-property
 *   merge is a future enhancement.
 * - Schema|bool|null unions (additionalProperties / unevaluatedProperties /
 *   contentSchema): sibling wins when it is a Schema or an explicit bool;
 *   otherwise the resolved value is kept.
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
            nullable: $sibling->nullable || $resolved->nullable,
            const: $sibling->hasConst ? $sibling->const : $resolved->const,
            hasConst: $sibling->hasConst || $resolved->hasConst,
            multipleOf: $sibling->multipleOf ?? $resolved->multipleOf,
            maximum: $sibling->maximum ?? $resolved->maximum,
            exclusiveMaximum: $sibling->exclusiveMaximum ?? $resolved->exclusiveMaximum,
            minimum: $sibling->minimum ?? $resolved->minimum,
            exclusiveMinimum: $sibling->exclusiveMinimum ?? $resolved->exclusiveMinimum,
            maxLength: $sibling->maxLength ?? $resolved->maxLength,
            minLength: $sibling->minLength ?? $resolved->minLength,
            pattern: $sibling->pattern ?? $resolved->pattern,
            maxItems: $sibling->maxItems ?? $resolved->maxItems,
            minItems: $sibling->minItems ?? $resolved->minItems,
            uniqueItems: $sibling->uniqueItems ?? $resolved->uniqueItems,
            maxProperties: $sibling->maxProperties ?? $resolved->maxProperties,
            minProperties: $sibling->minProperties ?? $resolved->minProperties,
            required: $this->mergeStringList($resolved->required, $sibling->required),
            allOf: $this->mergeSchemaList($resolved->allOf, $sibling->allOf),
            anyOf: $this->mergeSchemaList($resolved->anyOf, $sibling->anyOf),
            oneOf: $this->mergeSchemaList($resolved->oneOf, $sibling->oneOf),
            not: $sibling->not ?? $resolved->not,
            discriminator: $sibling->discriminator ?? $resolved->discriminator,
            properties: $this->mergeSchemaMap($resolved->properties, $sibling->properties),
            additionalProperties: $this->mergeSchemaOrBool($resolved->additionalProperties, $sibling->additionalProperties),
            unevaluatedProperties: $this->mergeSchemaOrBool($resolved->unevaluatedProperties, $sibling->unevaluatedProperties),
            items: $sibling->items ?? $resolved->items,
            prefixItems: $this->mergeSchemaList($resolved->prefixItems, $sibling->prefixItems),
            contains: $sibling->contains ?? $resolved->contains,
            minContains: $sibling->minContains ?? $resolved->minContains,
            maxContains: $sibling->maxContains ?? $resolved->maxContains,
            patternProperties: $this->mergeSchemaMap($resolved->patternProperties, $sibling->patternProperties),
            propertyNames: $sibling->propertyNames ?? $resolved->propertyNames,
            dependentSchemas: $this->mergeSchemaMap($resolved->dependentSchemas, $sibling->dependentSchemas),
            if: $sibling->if ?? $resolved->if,
            then: $sibling->then ?? $resolved->then,
            else: $sibling->else ?? $resolved->else,
            unevaluatedItems: $sibling->unevaluatedItems ?? $resolved->unevaluatedItems,
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
}
