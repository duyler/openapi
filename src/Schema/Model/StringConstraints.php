<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

final readonly class StringConstraints
{
    public function __construct(
        public ?int $maxLength = null,
        public ?int $minLength = null,
        public ?string $pattern = null,
    ) {}

    public function isEmpty(): bool
    {
        return null === $this->maxLength
            && null === $this->minLength
            && null === $this->pattern;
    }
}
