<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Tag implements JsonSerializable
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?ExternalDocs $externalDocs = null,
        public ?string $summary = null,
        public ?string $parent = null,
        public ?string $kind = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->externalDocs) {
            $data['externalDocs'] = $this->externalDocs;
        }

        if (null !== $this->summary) {
            $data['summary'] = $this->summary;
        }

        if (null !== $this->parent) {
            $data['parent'] = $this->parent;
        }

        if (null !== $this->kind) {
            $data['kind'] = $this->kind;
        }

        return $data;
    }
}
