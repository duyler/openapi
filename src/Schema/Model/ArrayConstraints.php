<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

/**
 * Sub-DTO grouping JSON Schema 2020-12 array constraint keywords.
 *
 * - `contains`, `minContains`, `maxContains` are array-presence keywords and
 *   are grouped here for cohesion even though `contains` is a Schema.
 * - `items`, `prefixItems`, `unevaluatedItems` are intentionally grouped here
 *   too because they describe array element shapes.
 */
final readonly class ArrayConstraints
{
    /**
     * @param list<Schema>|null                   $prefixItems
     */
    public function __construct(
        public Schema|bool|null $items = null,
        public ?array $prefixItems = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public Schema|bool|null $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public Schema|bool|null $unevaluatedItems = null,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->items
            && null === $this->prefixItems
            && null === $this->minItems
            && null === $this->maxItems
            && null === $this->uniqueItems
            && null === $this->contains
            && null === $this->minContains
            && null === $this->maxContains
            && null === $this->unevaluatedItems;
    }
}
