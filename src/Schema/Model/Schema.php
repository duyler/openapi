<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use JsonSerializable;
use Override;

/**
 * OpenAPI 3.2 / JSON Schema 2020-12 schema object.
 *
 * The historical facade keeps flat promoted properties so the 35+ existing
 * consumers (validators, compiler, ref resolver, tests) keep working. The
 * 56-field enumeration that used to live in jsonSerialize, in
 * CompilationCache::schemaToArray and in OpenApiBuilder::buildSchema now lives
 * exactly once in {@see SchemaFieldMetadata}, consumed by
 * {@see SchemaToArrayConverter} and {@see SchemaFromArrayConverter}.
 *
 * Sub-DTO accessors ({@see stringConstraints()}, {@see numericConstraints()},
 * {@see arrayConstraints()}, {@see objectConstraints()},
 * {@see compositionConstraints()}) expose the same fields as typed value
 * objects for new code and for downstream tasks that migrate consumers off the
 * flat API. They are derived lazily and never mutate the facade.
 */
final readonly class Schema implements JsonSerializable
{
    /**
     * @param string|list<string>|null $type
     * @param array<string, Schema>|null $properties
     * @param list<string>|null $required
     * @param list<Schema>|null $allOf
     * @param list<Schema>|null $anyOf
     * @param list<Schema>|null $oneOf
     * @param Schema|null $not
     * @param Schema|null $items
     * @param list<Schema>|null $prefixItems
     * @param array<string, Schema>|null $patternProperties
     * @param array<string, Schema>|null $dependentSchemas
     * @param Schema|bool|null $additionalProperties
     * @param Schema|bool|null $unevaluatedProperties
     * @param Schema|bool|null $contentSchema
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
        public ?Schema $not = null,
        public ?Discriminator $discriminator = null,
        public ?array $properties = null,
        public Schema|bool|null $additionalProperties = null,
        public Schema|bool|null $unevaluatedProperties = null,
        public ?Schema $items = null,
        public ?array $prefixItems = null,
        public ?Schema $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public ?array $patternProperties = null,
        public ?Schema $propertyNames = null,
        public ?array $dependentSchemas = null,
        public ?Schema $if = null,
        public ?Schema $then = null,
        public ?Schema $else = null,
        public ?Schema $unevaluatedItems = null,
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
     * @param string|list<string>|null $type
     * @param array<string, Schema>|null $properties
     * @param list<string>|null $required
     * @param list<Schema>|null $allOf
     * @param list<Schema>|null $anyOf
     * @param list<Schema>|null $oneOf
     * @param Schema|null $not
     * @param Schema|null $items
     * @param list<Schema>|null $prefixItems
     * @param array<string, Schema>|null $patternProperties
     * @param array<string, Schema>|null $dependentSchemas
     * @param Schema|bool|null $additionalProperties
     * @param Schema|bool|null $unevaluatedProperties
     * @param Schema|bool|null $contentSchema
     * @param list<mixed>|null $enum
     * @param array<string, mixed>|null $examples
     * @param Xml|null $xml
     */
    public function withOverrides(
        ?string $ref = null,
        ?string $refSummary = null,
        ?string $refDescription = null,
        ?string $format = null,
        ?string $title = null,
        ?string $description = null,
        string|int|float|bool|array|null $default = null,
        ?bool $hasDefault = null,
        ?bool $deprecated = null,
        ?bool $readOnly = null,
        ?bool $writeOnly = null,
        string|array|null $type = null,
        ?bool $nullable = null,
        string|int|float|bool|array|null $const = null,
        ?bool $hasConst = null,
        ?float $multipleOf = null,
        ?float $maximum = null,
        ?float $exclusiveMaximum = null,
        ?float $minimum = null,
        ?float $exclusiveMinimum = null,
        ?int $maxLength = null,
        ?int $minLength = null,
        ?string $pattern = null,
        ?int $maxItems = null,
        ?int $minItems = null,
        ?bool $uniqueItems = null,
        ?int $maxProperties = null,
        ?int $minProperties = null,
        ?array $required = null,
        ?array $allOf = null,
        ?array $anyOf = null,
        ?array $oneOf = null,
        ?Schema $not = null,
        ?Discriminator $discriminator = null,
        ?array $properties = null,
        Schema|bool|null $additionalProperties = null,
        Schema|bool|null $unevaluatedProperties = null,
        ?Schema $items = null,
        ?array $prefixItems = null,
        ?Schema $contains = null,
        ?int $minContains = null,
        ?int $maxContains = null,
        ?array $patternProperties = null,
        ?Schema $propertyNames = null,
        ?array $dependentSchemas = null,
        ?Schema $if = null,
        ?Schema $then = null,
        ?Schema $else = null,
        ?Schema $unevaluatedItems = null,
        string|int|float|bool|array|null $example = null,
        ?array $examples = null,
        ?array $enum = null,
        ?string $contentEncoding = null,
        ?string $contentMediaType = null,
        Schema|bool|null $contentSchema = null,
        ?string $jsonSchemaDialect = null,
        ?Xml $xml = null,
    ): self {
        return new self(
            ref: $ref ?? $this->ref,
            refSummary: $refSummary ?? $this->refSummary,
            refDescription: $refDescription ?? $this->refDescription,
            format: $format ?? $this->format,
            title: $title ?? $this->title,
            description: $description ?? $this->description,
            default: $default ?? $this->default,
            hasDefault: $hasDefault ?? $this->hasDefault,
            deprecated: $deprecated ?? $this->deprecated,
            readOnly: $readOnly ?? $this->readOnly,
            writeOnly: $writeOnly ?? $this->writeOnly,
            type: $type ?? $this->type,
            nullable: $nullable ?? $this->nullable,
            const: $const ?? $this->const,
            hasConst: $hasConst ?? $this->hasConst,
            multipleOf: $multipleOf ?? $this->multipleOf,
            maximum: $maximum ?? $this->maximum,
            exclusiveMaximum: $exclusiveMaximum ?? $this->exclusiveMaximum,
            minimum: $minimum ?? $this->minimum,
            exclusiveMinimum: $exclusiveMinimum ?? $this->exclusiveMinimum,
            maxLength: $maxLength ?? $this->maxLength,
            minLength: $minLength ?? $this->minLength,
            pattern: $pattern ?? $this->pattern,
            maxItems: $maxItems ?? $this->maxItems,
            minItems: $minItems ?? $this->minItems,
            uniqueItems: $uniqueItems ?? $this->uniqueItems,
            maxProperties: $maxProperties ?? $this->maxProperties,
            minProperties: $minProperties ?? $this->minProperties,
            required: $required ?? $this->required,
            allOf: $allOf ?? $this->allOf,
            anyOf: $anyOf ?? $this->anyOf,
            oneOf: $oneOf ?? $this->oneOf,
            not: $not ?? $this->not,
            discriminator: $discriminator ?? $this->discriminator,
            properties: $properties ?? $this->properties,
            additionalProperties: $additionalProperties ?? $this->additionalProperties,
            unevaluatedProperties: $unevaluatedProperties ?? $this->unevaluatedProperties,
            items: $items ?? $this->items,
            prefixItems: $prefixItems ?? $this->prefixItems,
            contains: $contains ?? $this->contains,
            minContains: $minContains ?? $this->minContains,
            maxContains: $maxContains ?? $this->maxContains,
            patternProperties: $patternProperties ?? $this->patternProperties,
            propertyNames: $propertyNames ?? $this->propertyNames,
            dependentSchemas: $dependentSchemas ?? $this->dependentSchemas,
            if: $if ?? $this->if,
            then: $then ?? $this->then,
            else: $else ?? $this->else,
            unevaluatedItems: $unevaluatedItems ?? $this->unevaluatedItems,
            example: $example ?? $this->example,
            examples: $examples ?? $this->examples,
            enum: $enum ?? $this->enum,
            contentEncoding: $contentEncoding ?? $this->contentEncoding,
            contentMediaType: $contentMediaType ?? $this->contentMediaType,
            contentSchema: $contentSchema ?? $this->contentSchema,
            jsonSchemaDialect: $jsonSchemaDialect ?? $this->jsonSchemaDialect,
            xml: $xml ?? $this->xml,
        );
    }

    /**
     * JSON Schema 2020-12 §8.2.3: when $ref is present, sibling keywords
     * are evaluated alongside the referenced schema. This method returns
     * a new schema where $sibling's constraints are merged into $this
     * (the resolved schema) per the strategy documented in
     * {@see SchemaSiblingMerger}. The $ref family is dropped because the
     * merger is invoked after reference resolution.
     */
    public function withSibling(Schema $sibling): self
    {
        return (new SchemaSiblingMerger())->merge($this, $sibling);
    }

    /**
     * Returns the string-constraint sub-DTO grouping minLength / maxLength /
     * pattern. Returns null when no string constraint is declared.
     *
     * `format` is intentionally not grouped here: it is shared between string
     * and numeric schemas and stays on the top-level facade.
     */
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

    /**
     * Returns the numeric-constraint sub-DTO grouping multipleOf / minimum /
     * maximum / exclusiveMinimum / exclusiveMaximum, or null when none are
     * declared.
     */
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

    /**
     * Returns the array-constraint sub-DTO grouping items / prefixItems /
     * minItems / maxItems / uniqueItems / contains / minContains / maxContains
     * / unevaluatedItems, or null when none are declared.
     */
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

    /**
     * Returns the object-constraint sub-DTO grouping properties / required /
     * minProperties / maxProperties / additionalProperties /
     * unevaluatedProperties / patternProperties / dependentSchemas /
     * propertyNames, or null when none are declared.
     */
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

    /**
     * Returns the composition sub-DTO grouping allOf / anyOf / oneOf / not /
     * if / then / else, or null when none are declared.
     */
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
