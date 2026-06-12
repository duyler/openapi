<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

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

    #[Override]
    public function jsonSerialize(): array
    {
        if (null !== $this->ref) {
            $data = ['$ref' => $this->ref];

            if (null !== $this->refSummary) {
                $data['summary'] = $this->refSummary;
            }

            if (null !== $this->refDescription) {
                $data['description'] = $this->refDescription;
            }

            return $data;
        }

        /** @var array<string, mixed> $data */
        $data = [];

        if (null !== $this->title) {
            $data['title'] = $this->title;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if ($this->hasDefault) {
            $data['default'] = $this->default;
        }

        if ($this->deprecated) {
            $data['deprecated'] = $this->deprecated;
        }

        if ($this->readOnly) {
            $data['readOnly'] = $this->readOnly;
        }

        if ($this->writeOnly) {
            $data['writeOnly'] = $this->writeOnly;
        }

        if (null !== $this->type) {
            $data['type'] = $this->type;
        }

        if ($this->nullable) {
            $data['nullable'] = $this->nullable;
        }

        if ($this->hasConst) {
            $data['const'] = $this->const;
        }

        if (null !== $this->multipleOf) {
            $data['multipleOf'] = $this->multipleOf;
        }

        if (null !== $this->maximum) {
            $data['maximum'] = $this->maximum;
        }

        if (null !== $this->exclusiveMaximum) {
            $data['exclusiveMaximum'] = $this->exclusiveMaximum;
        }

        if (null !== $this->minimum) {
            $data['minimum'] = $this->minimum;
        }

        if (null !== $this->exclusiveMinimum) {
            $data['exclusiveMinimum'] = $this->exclusiveMinimum;
        }

        if (null !== $this->maxLength) {
            $data['maxLength'] = $this->maxLength;
        }

        if (null !== $this->minLength) {
            $data['minLength'] = $this->minLength;
        }

        if (null !== $this->pattern) {
            $data['pattern'] = $this->pattern;
        }

        if (null !== $this->maxItems) {
            $data['maxItems'] = $this->maxItems;
        }

        if (null !== $this->minItems) {
            $data['minItems'] = $this->minItems;
        }

        if (null !== $this->uniqueItems) {
            $data['uniqueItems'] = $this->uniqueItems;
        }

        if (null !== $this->maxProperties) {
            $data['maxProperties'] = $this->maxProperties;
        }

        if (null !== $this->minProperties) {
            $data['minProperties'] = $this->minProperties;
        }

        if (null !== $this->required) {
            $data['required'] = $this->required;
        }

        if (null !== $this->allOf) {
            $data['allOf'] = $this->allOf;
        }

        if (null !== $this->anyOf) {
            $data['anyOf'] = $this->anyOf;
        }

        if (null !== $this->oneOf) {
            $data['oneOf'] = $this->oneOf;
        }

        if (null !== $this->not) {
            $data['not'] = $this->not;
        }

        if (null !== $this->discriminator) {
            $data['discriminator'] = $this->discriminator;
        }

        if (null !== $this->properties) {
            $data['properties'] = $this->properties;
        }

        if (null !== $this->additionalProperties) {
            $data['additionalProperties'] = $this->additionalProperties;
        }

        if (null !== $this->unevaluatedProperties) {
            $data['unevaluatedProperties'] = $this->unevaluatedProperties;
        }

        if (null !== $this->items) {
            $data['items'] = $this->items;
        }

        if (null !== $this->prefixItems) {
            $data['prefixItems'] = $this->prefixItems;
        }

        if (null !== $this->contains) {
            $data['contains'] = $this->contains;
        }

        if (null !== $this->minContains) {
            $data['minContains'] = $this->minContains;
        }

        if (null !== $this->maxContains) {
            $data['maxContains'] = $this->maxContains;
        }

        if (null !== $this->patternProperties) {
            $data['patternProperties'] = $this->patternProperties;
        }

        if (null !== $this->propertyNames) {
            $data['propertyNames'] = $this->propertyNames;
        }

        if (null !== $this->dependentSchemas) {
            $data['dependentSchemas'] = $this->dependentSchemas;
        }

        if (null !== $this->if) {
            $data['if'] = $this->if;
        }

        if (null !== $this->then) {
            $data['then'] = $this->then;
        }

        if (null !== $this->else) {
            $data['else'] = $this->else;
        }

        if (null !== $this->unevaluatedItems) {
            $data['unevaluatedItems'] = $this->unevaluatedItems;
        }

        if (null !== $this->example) {
            $data['example'] = $this->example;
        }

        if (null !== $this->examples) {
            $data['examples'] = $this->examples;
        }

        if (null !== $this->enum) {
            $data['enum'] = $this->enum;
        }

        if (null !== $this->format) {
            $data['format'] = $this->format;
        }

        if (null !== $this->contentEncoding) {
            $data['contentEncoding'] = $this->contentEncoding;
        }

        if (null !== $this->contentMediaType) {
            $data['contentMediaType'] = $this->contentMediaType;
        }

        if (null !== $this->contentSchema) {
            $data['contentSchema'] = $this->contentSchema;
        }

        if (null !== $this->jsonSchemaDialect) {
            $data['$schema'] = $this->jsonSchemaDialect;
        }

        if (null !== $this->xml) {
            $data['xml'] = $this->xml;
        }

        return $data;
    }
}
