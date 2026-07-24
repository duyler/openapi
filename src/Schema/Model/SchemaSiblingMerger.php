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

    /** @return list<Schema> */
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

    /** @return list<Schema> */
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
