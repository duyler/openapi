<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class RequestBody implements JsonSerializable
{
    public function __construct(
        public ?string $description = null,
        public ?Content $content = null,
        public bool $required = false,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->required) {
            $data['required'] = $this->required;
        }

        return $data;
    }
}
