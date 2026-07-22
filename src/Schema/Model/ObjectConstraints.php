<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

/**
 * Sub-DTO grouping JSON Schema 2020-12 object constraint keywords.
 *
 * - `additionalProperties` and `unevaluatedProperties` accept `Schema|bool|null`
 *   per spec.
 */
final readonly class ObjectConstraints
{
    /**
     * @param array<string, Schema>|null          $properties
     * @param list<string>|null                    $required
     * @param Schema|bool|null                     $additionalProperties
     * @param Schema|bool|null                     $unevaluatedProperties
     * @param array<string, Schema>|null          $patternProperties
     * @param array<string, Schema>|null          $dependentSchemas
     * @param Schema|bool|null                     $propertyNames
     */
    public function __construct(
        public ?array $properties = null,
        public ?array $required = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public Schema|bool|null $additionalProperties = null,
        public Schema|bool|null $unevaluatedProperties = null,
        public ?array $patternProperties = null,
        public ?array $dependentSchemas = null,
        public Schema|bool|null $propertyNames = null,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->properties
            && null === $this->required
            && null === $this->minProperties
            && null === $this->maxProperties
            && null === $this->additionalProperties
            && null === $this->unevaluatedProperties
            && null === $this->patternProperties
            && null === $this->dependentSchemas
            && null === $this->propertyNames;
    }
}
