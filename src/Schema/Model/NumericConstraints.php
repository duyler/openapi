<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

final readonly class NumericConstraints
{
    public function __construct(
        public ?float $multipleOf = null,
        public ?float $maximum = null,
        public ?float $exclusiveMaximum = null,
        public ?float $minimum = null,
        public ?float $exclusiveMinimum = null,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->multipleOf
            && null === $this->maximum
            && null === $this->exclusiveMaximum
            && null === $this->minimum
            && null === $this->exclusiveMinimum;
    }
}
