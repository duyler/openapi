<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

/**
 * Sub-DTO grouping JSON Schema 2020-12 string constraint keywords.
 *
 * The fields mirror OpenAPI 3.2 / JSON Schema 2020-12 spec semantics:
 * - `format` is intentionally excluded here; it is shared between string and
 *   numeric schemas and stays on the top-level Schema facade.
 */
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
