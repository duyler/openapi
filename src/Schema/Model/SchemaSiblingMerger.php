<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Model\Internal\BoundSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\CompositionSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\ScalarSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\SiblingApplicatorFields;
use Duyler\OpenApi\Schema\Model\Internal\SiblingMergeContext;

use function array_merge;
use function array_unique;
use function array_values;

final readonly class SchemaSiblingMerger
{
    public function __construct(
        private ScalarSiblingMerger $scalarMerger = new ScalarSiblingMerger(),
        private BoundSiblingMerger $boundMerger = new BoundSiblingMerger(),
        private CompositionSiblingMerger $compositionMerger = new CompositionSiblingMerger(),
    ) {}

    public function merge(Schema $resolved, Schema $sibling): Schema
    {
        $context = new SiblingMergeContext($resolved, $sibling);
        $bound = $this->boundMerger->merge($context);
        $applicators = SiblingApplicatorFields::merged($this->compositionMerger, $context, $bound);

        return $this->build($resolved, $sibling, $bound, $applicators);
    }

    public function mergeTargetSide(Schema $resolved, Schema $sibling): Schema
    {
        $context = new SiblingMergeContext($resolved, $sibling);
        $bound = $this->boundMerger->merge($context);

        return $this->build($resolved, $sibling, $bound, SiblingApplicatorFields::fromResolved($resolved));
    }

    /**
     * @param array{
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
     * } $bound
     */
    private function build(Schema $resolved, Schema $sibling, array $bound, SiblingApplicatorFields $applicators): Schema
    {
        $context = new SiblingMergeContext($resolved, $sibling);
        $scalar = $this->scalarMerger->merge($context);

        return new Schema(
            ref: null,
            refSummary: null,
            refDescription: null,
            format: $scalar['format'],
            title: $sibling->refSummary ?? $sibling->title ?? $resolved->title,
            description: $sibling->refDescription ?? $sibling->description ?? $resolved->description,
            default: $sibling->hasDefault ? $sibling->default : $resolved->default,
            hasDefault: $sibling->hasDefault || $resolved->hasDefault,
            deprecated: $sibling->deprecated || $resolved->deprecated,
            readOnly: $sibling->readOnly || $resolved->readOnly,
            writeOnly: $sibling->writeOnly || $resolved->writeOnly,
            type: $scalar['type'],
            nullable: $scalar['nullable'],
            const: $scalar['const'],
            hasConst: $scalar['hasConst'],
            multipleOf: $scalar['multipleOf'],
            maximum: $bound['maximum'],
            exclusiveMaximum: $bound['exclusiveMaximum'],
            minimum: $bound['minimum'],
            exclusiveMinimum: $bound['exclusiveMinimum'],
            maxLength: $bound['maxLength'],
            minLength: $bound['minLength'],
            pattern: $scalar['pattern'],
            maxItems: $bound['maxItems'],
            minItems: $bound['minItems'],
            uniqueItems: $bound['uniqueItems'],
            maxProperties: $bound['maxProperties'],
            minProperties: $bound['minProperties'],
            required: $this->mergeRequired($resolved->required, $sibling->required),
            allOf: $applicators->allOf,
            anyOf: $applicators->anyOf,
            oneOf: $applicators->oneOf,
            not: $applicators->not,
            discriminator: $sibling->discriminator ?? $resolved->discriminator,
            properties: $applicators->properties,
            additionalProperties: $applicators->additionalProperties,
            unevaluatedProperties: $applicators->unevaluatedProperties,
            items: $applicators->items,
            prefixItems: $applicators->prefixItems,
            contains: $applicators->contains,
            minContains: $sibling->minContains ?? $resolved->minContains,
            maxContains: $sibling->maxContains ?? $resolved->maxContains,
            patternProperties: $applicators->patternProperties,
            propertyNames: $applicators->propertyNames,
            dependentRequired: $this->mergeDependentRequired($resolved->dependentRequired, $sibling->dependentRequired),
            dependentSchemas: $applicators->dependentSchemas,
            if: $applicators->if,
            then: $applicators->then,
            else: $applicators->else,
            unevaluatedItems: $applicators->unevaluatedItems,
            example: $sibling->example ?? $resolved->example,
            examples: $this->scalarMerger->mergeExamples($resolved->examples, $sibling->examples),
            enum: $scalar['enum'],
            contentEncoding: $sibling->contentEncoding ?? $resolved->contentEncoding,
            contentMediaType: $sibling->contentMediaType ?? $resolved->contentMediaType,
            contentSchema: $bound['contentSchema'],
            jsonSchemaDialect: $sibling->jsonSchemaDialect ?? $resolved->jsonSchemaDialect,
            xml: $sibling->xml ?? $resolved->xml,
            id: $resolved->id,
            anchor: $resolved->anchor,
            dynamicAnchor: $resolved->dynamicAnchor,
            dynamicRef: $sibling->dynamicRef ?? $resolved->dynamicRef,
            comment: $resolved->comment,
            vocabulary: $resolved->vocabulary,
            defs: $resolved->defs,
            extras: $this->scalarMerger->mergeMixedMap($resolved->extras, $sibling->extras),
        );
    }

    /**
     * @param ?list<string> $resolved
     * @param ?list<string> $sibling
     * @return ?list<string>
     */
    private function mergeRequired(?array $resolved, ?array $sibling): ?array
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
     * @param ?array<string, list<string>> $resolved
     * @param ?array<string, list<string>> $sibling
     * @return ?array<string, list<string>>
     */
    private function mergeDependentRequired(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        $merged = $resolved;

        foreach ($sibling as $property => $requiredProperties) {
            $merged[$property] = array_values(array_unique(array_merge(
                $merged[$property] ?? [],
                $requiredProperties,
            )));
        }

        return $merged;
    }
}
