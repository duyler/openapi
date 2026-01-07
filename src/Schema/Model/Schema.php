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
     * @param list<mixed>|null $enum
     * @param array<string, mixed>|null $examples
     */
    public function __construct(
        public ?string $ref = null,
        public ?string $format = null,
        public ?string $title = null,
        public ?string $description = null,
        public mixed $default = null,
        public bool $deprecated = false,
        public string|array|null $type = null,
        public mixed $const = null,
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
        public ?bool $unevaluatedProperties = null,
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
        public mixed $example = null,
        public ?array $examples = null,
        public ?array $enum = null,
        public ?string $contentEncoding = null,
        public ?string $contentMediaType = null,
        public ?string $contentSchema = null,
        public ?string $jsonSchemaDialect = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->ref !== null) {
            $data['$ref'] = $this->ref;
        }

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->default !== null) {
            $data['default'] = $this->default;
        }

        if ($this->deprecated) {
            $data['deprecated'] = $this->deprecated;
        }

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        if ($this->const !== null) {
            $data['const'] = $this->const;
        }

        if ($this->multipleOf !== null) {
            $data['multipleOf'] = $this->multipleOf;
        }

        if ($this->maximum !== null) {
            $data['maximum'] = $this->maximum;
        }

        if ($this->exclusiveMaximum !== null) {
            $data['exclusiveMaximum'] = $this->exclusiveMaximum;
        }

        if ($this->minimum !== null) {
            $data['minimum'] = $this->minimum;
        }

        if ($this->exclusiveMinimum !== null) {
            $data['exclusiveMinimum'] = $this->exclusiveMinimum;
        }

        if ($this->maxLength !== null) {
            $data['maxLength'] = $this->maxLength;
        }

        if ($this->minLength !== null) {
            $data['minLength'] = $this->minLength;
        }

        if ($this->pattern !== null) {
            $data['pattern'] = $this->pattern;
        }

        if ($this->maxItems !== null) {
            $data['maxItems'] = $this->maxItems;
        }

        if ($this->minItems !== null) {
            $data['minItems'] = $this->minItems;
        }

        if ($this->uniqueItems !== null) {
            $data['uniqueItems'] = $this->uniqueItems;
        }

        if ($this->maxProperties !== null) {
            $data['maxProperties'] = $this->maxProperties;
        }

        if ($this->minProperties !== null) {
            $data['minProperties'] = $this->minProperties;
        }

        if ($this->required !== null) {
            $data['required'] = $this->required;
        }

        if ($this->allOf !== null) {
            $data['allOf'] = $this->allOf;
        }

        if ($this->anyOf !== null) {
            $data['anyOf'] = $this->anyOf;
        }

        if ($this->oneOf !== null) {
            $data['oneOf'] = $this->oneOf;
        }

        if ($this->not !== null) {
            $data['not'] = $this->not;
        }

        if ($this->discriminator !== null) {
            $data['discriminator'] = $this->discriminator;
        }

        if ($this->properties !== null) {
            $data['properties'] = $this->properties;
        }

        if ($this->additionalProperties !== null) {
            $data['additionalProperties'] = $this->additionalProperties;
        }

        if ($this->unevaluatedProperties !== null) {
            $data['unevaluatedProperties'] = $this->unevaluatedProperties;
        }

        if ($this->items !== null) {
            $data['items'] = $this->items;
        }

        if ($this->prefixItems !== null) {
            $data['prefixItems'] = $this->prefixItems;
        }

        if ($this->contains !== null) {
            $data['contains'] = $this->contains;
        }

        if ($this->minContains !== null) {
            $data['minContains'] = $this->minContains;
        }

        if ($this->maxContains !== null) {
            $data['maxContains'] = $this->maxContains;
        }

        if ($this->patternProperties !== null) {
            $data['patternProperties'] = $this->patternProperties;
        }

        if ($this->propertyNames !== null) {
            $data['propertyNames'] = $this->propertyNames;
        }

        if ($this->dependentSchemas !== null) {
            $data['dependentSchemas'] = $this->dependentSchemas;
        }

        if ($this->if !== null) {
            $data['if'] = $this->if;
        }

        if ($this->then !== null) {
            $data['then'] = $this->then;
        }

        if ($this->else !== null) {
            $data['else'] = $this->else;
        }

        if ($this->unevaluatedItems !== null) {
            $data['unevaluatedItems'] = $this->unevaluatedItems;
        }

        if ($this->example !== null) {
            $data['example'] = $this->example;
        }

        if ($this->examples !== null) {
            $data['examples'] = $this->examples;
        }

        if ($this->enum !== null) {
            $data['enum'] = $this->enum;
        }

        if ($this->format !== null) {
            $data['format'] = $this->format;
        }

        if ($this->contentEncoding !== null) {
            $data['contentEncoding'] = $this->contentEncoding;
        }

        if ($this->contentMediaType !== null) {
            $data['contentMediaType'] = $this->contentMediaType;
        }

        if ($this->contentSchema !== null) {
            $data['contentSchema'] = $this->contentSchema;
        }

        if ($this->jsonSchemaDialect !== null) {
            $data['$schema'] = $this->jsonSchemaDialect;
        }

        return $data;
    }
}
