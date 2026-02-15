<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Example implements JsonSerializable
{
    public function __construct(
        public ?string $summary = null,
        public ?string $description = null,
        public mixed $value = null,
        public ?string $externalValue = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->summary !== null) {
            $data['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->value !== null) {
            $data['value'] = $this->value;
        }

        if ($this->externalValue !== null) {
            $data['externalValue'] = $this->externalValue;
        }

        return $data;
    }
}
