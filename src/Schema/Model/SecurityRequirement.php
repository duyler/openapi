<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class SecurityRequirement implements JsonSerializable
{
    /**
     * @param list<array<string, list<string>>> $requirements
     */
    public function __construct(
        public array $requirements,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        return $this->requirements;
    }
}
