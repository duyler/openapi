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
        public ?Schema $not = null,
        public ?Schema $if = null,
        public ?Schema $then = null,
        public ?Schema $else = null,
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
