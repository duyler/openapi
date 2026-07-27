<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Model\Internal\BoundSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\CompositionSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\ScalarSiblingMerger;
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

        $scalar = $this->scalarMerger->merge($context);
        $bound = $this->boundMerger->merge($context);
        $composition = $this->compositionMerger->merge($context);

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
            allOf: $composition['allOf'],
            anyOf: $composition['anyOf'],
            oneOf: $composition['oneOf'],
            not: $bound['not'],
            discriminator: $sibling->discriminator ?? $resolved->discriminator,
            properties: $composition['properties'],
            additionalProperties: $bound['additionalProperties'],
            unevaluatedProperties: $bound['unevaluatedProperties'],
            items: $bound['items'],
            prefixItems: $bound['prefixItems'],
            contains: $bound['contains'],
            minContains: $sibling->minContains ?? $resolved->minContains,
            maxContains: $sibling->maxContains ?? $resolved->maxContains,
            patternProperties: $composition['patternProperties'],
            propertyNames: $bound['propertyNames'],
            dependentSchemas: $composition['dependentSchemas'],
            if: $composition['if'],
            then: $composition['then'],
            else: $composition['else'],
            unevaluatedItems: $bound['unevaluatedItems'],
            example: $sibling->example ?? $resolved->example,
            examples: $this->mergeExamples($resolved->examples, $sibling->examples),
            enum: $scalar['enum'],
            contentEncoding: $sibling->contentEncoding ?? $resolved->contentEncoding,
            contentMediaType: $sibling->contentMediaType ?? $resolved->contentMediaType,
            contentSchema: $bound['contentSchema'],
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
     * @param ?array<string, mixed> $resolved
     * @param ?array<string, mixed> $sibling
     *
     * @return ?array<string, mixed>
     */
    private function mergeExamples(?array $resolved, ?array $sibling): ?array
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
