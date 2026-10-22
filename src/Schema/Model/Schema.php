<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Model\Internal\ArrayFields;
use Duyler\OpenApi\Schema\Model\Internal\CompositionFields;
use Duyler\OpenApi\Schema\Model\Internal\ObjectFields;
use Duyler\OpenApi\Schema\Model\Internal\ScalarFields;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use JsonSerializable;
use Override;

final readonly class Schema implements JsonSerializable
{
    /**
     * @param string|list<string>|null $type
     * @param array<string, Schema>|null $properties
     * @param list<string>|null $required
     * @param list<Schema>|null $allOf
     * @param list<Schema>|null $anyOf
     * @param list<Schema>|null $oneOf
     * @param Schema|bool|null $not
     * @param Schema|bool|null $items
     * @param list<Schema>|null $prefixItems
     * @param array<string, Schema>|null $patternProperties
     * @param array<string, Schema>|null $dependentSchemas
     * @param Schema|bool|null $additionalProperties
     * @param Schema|bool|null $unevaluatedProperties
     * @param Schema|bool|null $contentSchema
     * @param Schema|bool|null $contains
     * @param Schema|bool|null $propertyNames
     * @param Schema|bool|null $if
     * @param Schema|bool|null $then
     * @param Schema|bool|null $else
     * @param Schema|bool|null $unevaluatedItems
     * @param list<mixed>|null $enum
     * @param array<string, mixed>|null $examples
     * @param Xml|null $xml
     */
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $format = null,
        public ?string $title = null,
        public ?string $description = null,
        public string|int|float|bool|array|null $default = null,
        public bool $hasDefault = false,
        public bool $deprecated = false,
        public bool $readOnly = false,
        public bool $writeOnly = false,
        public string|array|null $type = null,
        public bool $nullable = false,
        public string|int|float|bool|array|null $const = null,
        public bool $hasConst = false,
        public ?float $multipleOf = null,
        public ?float $maximum = null,
        public ?float $exclusiveMaximum = null,
        public ?float $minimum = null,
        public ?float $exclusiveMinimum = null,
        public ?int $maxLength = null,
        public ?int $minLength = null,
        public ?string $pattern = null,
        public ?int $maxItems = null,
        public ?int $minItems = null,
        public ?bool $uniqueItems = null,
        public ?int $maxProperties = null,
        public ?int $minProperties = null,
        public ?array $required = null,
        public ?array $allOf = null,
        public ?array $anyOf = null,
        public ?array $oneOf = null,
        public Schema|bool|null $not = null,
        public ?Discriminator $discriminator = null,
        public ?array $properties = null,
        public Schema|bool|null $additionalProperties = null,
        public Schema|bool|null $unevaluatedProperties = null,
        public Schema|bool|null $items = null,
        public ?array $prefixItems = null,
        public Schema|bool|null $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public ?array $patternProperties = null,
        public Schema|bool|null $propertyNames = null,
        public ?array $dependentSchemas = null,
        public Schema|bool|null $if = null,
        public Schema|bool|null $then = null,
        public Schema|bool|null $else = null,
        public Schema|bool|null $unevaluatedItems = null,
        public string|int|float|bool|array|null $example = null,
        public ?array $examples = null,
        public ?array $enum = null,
        public ?string $contentEncoding = null,
        public ?string $contentMediaType = null,
        public Schema|bool|null $contentSchema = null,
        public ?string $jsonSchemaDialect = null,
        public ?Xml $xml = null,
    ) {}

    /**
     * @param ?bool $nullable
     */
    public static function fromConstraintGroups(
        ScalarFields $scalar,
        ArrayFields $array,
        ObjectFields $object,
        CompositionFields $composition,
        ?bool $nullable = null,
    ): self {
        return new self(
            ref: $scalar->ref,
            refSummary: $scalar->refSummary,
            refDescription: $scalar->refDescription,
            format: $scalar->format,
            title: $scalar->title,
            description: $scalar->description,
            default: $scalar->default,
            hasDefault: $scalar->hasDefault ?? false,
            deprecated: $scalar->deprecated ?? false,
            readOnly: $scalar->readOnly ?? false,
            writeOnly: $scalar->writeOnly ?? false,
            type: $scalar->type,
            nullable: $nullable ?? false,
            const: $scalar->const,
            hasConst: $scalar->hasConst ?? false,
            multipleOf: $scalar->multipleOf,
            maximum: $scalar->maximum,
            exclusiveMaximum: $scalar->exclusiveMaximum,
            minimum: $scalar->minimum,
            exclusiveMinimum: $scalar->exclusiveMinimum,
            maxLength: $scalar->maxLength,
            minLength: $scalar->minLength,
            pattern: $scalar->pattern,
            maxItems: $array->maxItems,
            minItems: $array->minItems,
            uniqueItems: $array->uniqueItems,
            maxProperties: $object->maxProperties,
            minProperties: $object->minProperties,
            required: $object->required,
            allOf: $composition->allOf,
            anyOf: $composition->anyOf,
            oneOf: $composition->oneOf,
            not: $composition->not,
            discriminator: $scalar->discriminator,
            properties: $object->properties,
            additionalProperties: $object->additionalProperties,
            unevaluatedProperties: $object->unevaluatedProperties,
            items: $array->items,
            prefixItems: $array->prefixItems,
            contains: $array->contains,
            minContains: $array->minContains,
            maxContains: $array->maxContains,
            patternProperties: $object->patternProperties,
            propertyNames: $object->propertyNames,
            dependentSchemas: $object->dependentSchemas,
            if: $composition->if,
            then: $composition->then,
            else: $composition->else,
            unevaluatedItems: $array->unevaluatedItems,
            example: $scalar->example,
            examples: $scalar->examples,
            enum: $scalar->enum,
            contentEncoding: $scalar->contentEncoding,
            contentMediaType: $scalar->contentMediaType,
            contentSchema: $scalar->contentSchema,
            jsonSchemaDialect: $scalar->jsonSchemaDialect,
            xml: $scalar->xml,
        );
    }

    public function withOverrideGroups(
        ScalarFields $scalar,
        ArrayFields $array,
        ObjectFields $object,
        CompositionFields $composition,
        ?bool $nullable = null,
    ): self {
        return new self(
            ref: $scalar->ref ?? $this->ref,
            refSummary: $scalar->refSummary ?? $this->refSummary,
            refDescription: $scalar->refDescription ?? $this->refDescription,
            format: $scalar->format ?? $this->format,
            title: $scalar->title ?? $this->title,
            description: $scalar->description ?? $this->description,
            default: $scalar->default ?? $this->default,
            hasDefault: $scalar->hasDefault ?? $this->hasDefault,
            deprecated: $scalar->deprecated ?? $this->deprecated,
            readOnly: $scalar->readOnly ?? $this->readOnly,
            writeOnly: $scalar->writeOnly ?? $this->writeOnly,
            type: $scalar->type ?? $this->type,
            nullable: $nullable ?? $this->nullable,
            const: $scalar->const ?? $this->const,
            hasConst: $scalar->hasConst ?? $this->hasConst,
            multipleOf: $scalar->multipleOf ?? $this->multipleOf,
            maximum: $scalar->maximum ?? $this->maximum,
            exclusiveMaximum: $scalar->exclusiveMaximum ?? $this->exclusiveMaximum,
            minimum: $scalar->minimum ?? $this->minimum,
            exclusiveMinimum: $scalar->exclusiveMinimum ?? $this->exclusiveMinimum,
            maxLength: $scalar->maxLength ?? $this->maxLength,
            minLength: $scalar->minLength ?? $this->minLength,
            pattern: $scalar->pattern ?? $this->pattern,
            maxItems: $array->maxItems ?? $this->maxItems,
            minItems: $array->minItems ?? $this->minItems,
            uniqueItems: $array->uniqueItems ?? $this->uniqueItems,
            maxProperties: $object->maxProperties ?? $this->maxProperties,
            minProperties: $object->minProperties ?? $this->minProperties,
            required: $object->required ?? $this->required,
            allOf: $composition->allOf ?? $this->allOf,
            anyOf: $composition->anyOf ?? $this->anyOf,
            oneOf: $composition->oneOf ?? $this->oneOf,
            not: $composition->not ?? $this->not,
            discriminator: $scalar->discriminator ?? $this->discriminator,
            properties: $object->properties ?? $this->properties,
            additionalProperties: $object->additionalProperties ?? $this->additionalProperties,
            unevaluatedProperties: $object->unevaluatedProperties ?? $this->unevaluatedProperties,
            items: $array->items ?? $this->items,
            prefixItems: $array->prefixItems ?? $this->prefixItems,
            contains: $array->contains ?? $this->contains,
            minContains: $array->minContains ?? $this->minContains,
            maxContains: $array->maxContains ?? $this->maxContains,
            patternProperties: $object->patternProperties ?? $this->patternProperties,
            propertyNames: $object->propertyNames ?? $this->propertyNames,
            dependentSchemas: $object->dependentSchemas ?? $this->dependentSchemas,
            if: $composition->if ?? $this->if,
            then: $composition->then ?? $this->then,
            else: $composition->else ?? $this->else,
            unevaluatedItems: $array->unevaluatedItems ?? $this->unevaluatedItems,
            example: $scalar->example ?? $this->example,
            examples: $scalar->examples ?? $this->examples,
            enum: $scalar->enum ?? $this->enum,
            contentEncoding: $scalar->contentEncoding ?? $this->contentEncoding,
            contentMediaType: $scalar->contentMediaType ?? $this->contentMediaType,
            contentSchema: $scalar->contentSchema ?? $this->contentSchema,
            jsonSchemaDialect: $scalar->jsonSchemaDialect ?? $this->jsonSchemaDialect,
            xml: $scalar->xml ?? $this->xml,
        );
    }

    public function withSibling(Schema $sibling): self
    {
        return new SchemaSiblingMerger()->merge($this, $sibling);
    }

    public function stringConstraints(): ?StringConstraints
    {
        if (null === $this->minLength && null === $this->maxLength && null === $this->pattern) {
            return null;
        }

        return new StringConstraints(
            maxLength: $this->maxLength,
            minLength: $this->minLength,
            pattern: $this->pattern,
        );
    }

    public function numericConstraints(): ?NumericConstraints
    {
        if (
            null === $this->multipleOf
            && null === $this->maximum
            && null === $this->exclusiveMaximum
            && null === $this->minimum
            && null === $this->exclusiveMinimum
        ) {
            return null;
        }

        return new NumericConstraints(
            multipleOf: $this->multipleOf,
            maximum: $this->maximum,
            exclusiveMaximum: $this->exclusiveMaximum,
            minimum: $this->minimum,
            exclusiveMinimum: $this->exclusiveMinimum,
        );
    }

    public function arrayConstraints(): ?ArrayConstraints
    {
        if (
            null === $this->items
            && null === $this->prefixItems
            && null === $this->minItems
            && null === $this->maxItems
            && null === $this->uniqueItems
            && null === $this->contains
            && null === $this->minContains
            && null === $this->maxContains
            && null === $this->unevaluatedItems
        ) {
            return null;
        }

        return new ArrayConstraints(
            items: $this->items,
            prefixItems: $this->prefixItems,
            minItems: $this->minItems,
            maxItems: $this->maxItems,
            uniqueItems: $this->uniqueItems,
            contains: $this->contains,
            minContains: $this->minContains,
            maxContains: $this->maxContains,
            unevaluatedItems: $this->unevaluatedItems,
        );
    }

    public function objectConstraints(): ?ObjectConstraints
    {
        if (
            null === $this->properties
            && null === $this->required
            && null === $this->minProperties
            && null === $this->maxProperties
            && null === $this->additionalProperties
            && null === $this->unevaluatedProperties
            && null === $this->patternProperties
            && null === $this->dependentSchemas
            && null === $this->propertyNames
        ) {
            return null;
        }

        return new ObjectConstraints(
            properties: $this->properties,
            required: $this->required,
            minProperties: $this->minProperties,
            maxProperties: $this->maxProperties,
            additionalProperties: $this->additionalProperties,
            unevaluatedProperties: $this->unevaluatedProperties,
            patternProperties: $this->patternProperties,
            dependentSchemas: $this->dependentSchemas,
            propertyNames: $this->propertyNames,
        );
    }

    public function compositionConstraints(): ?CompositionConstraints
    {
        if (
            null === $this->allOf
            && null === $this->anyOf
            && null === $this->oneOf
            && null === $this->not
            && null === $this->if
            && null === $this->then
            && null === $this->else
        ) {
            return null;
        }

        return new CompositionConstraints(
            allOf: $this->allOf,
            anyOf: $this->anyOf,
            oneOf: $this->oneOf,
            not: $this->not,
            if: $this->if,
            then: $this->then,
            else: $this->else,
        );
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return new SchemaToArrayConverter()->toWireArray($this);
    }
}
