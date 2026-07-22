<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

final readonly class CompositionConstraints
{
    /**
     * @param list<Schema>|null $allOf
     * @param list<Schema>|null $anyOf
     * @param list<Schema>|null $oneOf
     */
    public function __construct(
        public ?array $allOf = null,
        public ?array $anyOf = null,
        public ?array $oneOf = null,
        public Schema|bool|null $not = null,
        public Schema|bool|null $if = null,
        public Schema|bool|null $then = null,
        public Schema|bool|null $else = null,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->allOf
            && null === $this->anyOf
            && null === $this->oneOf
            && null === $this->not
            && null === $this->if
            && null === $this->then
            && null === $this->else;
    }
}
