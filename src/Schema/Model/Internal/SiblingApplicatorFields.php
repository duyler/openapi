<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/** @internal */
final readonly class SiblingApplicatorFields
{
    /**
     * @param list<Schema>|null $allOf
     * @param list<Schema>|null $anyOf
     * @param list<Schema>|null $oneOf
     * @param array<string, Schema>|null $properties
     * @param list<Schema>|null $prefixItems
     * @param array<string, Schema>|null $patternProperties
     * @param array<string, Schema>|null $dependentSchemas
     */
    private function __construct(
        public ?array $allOf,
        public ?array $anyOf,
        public ?array $oneOf,
        public Schema|bool|null $not,
        public ?array $properties,
        public Schema|bool|null $additionalProperties,
        public Schema|bool|null $unevaluatedProperties,
        public Schema|bool|null $items,
        public ?array $prefixItems,
        public Schema|bool|null $contains,
        public ?array $patternProperties,
        public Schema|bool|null $propertyNames,
        public ?array $dependentSchemas,
        public Schema|bool|null $if,
        public Schema|bool|null $then,
        public Schema|bool|null $else,
        public Schema|bool|null $unevaluatedItems,
    ) {}

    public static function fromResolved(Schema $resolved): self
    {
        return new self(
            allOf: $resolved->allOf,
            anyOf: $resolved->anyOf,
            oneOf: $resolved->oneOf,
            not: $resolved->not,
            properties: $resolved->properties,
            additionalProperties: $resolved->additionalProperties,
            unevaluatedProperties: $resolved->unevaluatedProperties,
            items: $resolved->items,
            prefixItems: $resolved->prefixItems,
            contains: $resolved->contains,
            patternProperties: $resolved->patternProperties,
            propertyNames: $resolved->propertyNames,
            dependentSchemas: $resolved->dependentSchemas,
            if: $resolved->if,
            then: $resolved->then,
            else: $resolved->else,
            unevaluatedItems: $resolved->unevaluatedItems,
        );
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
    public static function merged(
        CompositionSiblingMerger $compositionMerger,
        SiblingMergeContext $context,
        array $bound,
    ): self {
        $composition = $compositionMerger->merge($context);

        return new self(
            allOf: $composition['allOf'],
            anyOf: $composition['anyOf'],
            oneOf: $composition['oneOf'],
            not: $bound['not'],
            properties: $composition['properties'],
            additionalProperties: $bound['additionalProperties'],
            unevaluatedProperties: $bound['unevaluatedProperties'],
            items: $bound['items'],
            prefixItems: $bound['prefixItems'],
            contains: $bound['contains'],
            patternProperties: $composition['patternProperties'],
            propertyNames: $bound['propertyNames'],
            dependentSchemas: $composition['dependentSchemas'],
            if: $composition['if'],
            then: $composition['then'],
            else: $composition['else'],
            unevaluatedItems: $bound['unevaluatedItems'],
        );
    }
}
