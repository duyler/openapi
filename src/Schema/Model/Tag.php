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
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs;
        }

        return $data;
    }
}
