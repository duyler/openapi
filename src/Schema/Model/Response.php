<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Response implements JsonSerializable
{
    public function __construct(
        public ?string $ref = null,
        public ?string $description = null,
        public ?Headers $headers = null,
        public ?Content $content = null,
        public ?Links $links = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->ref !== null) {
            $data['$ref'] = $this->ref;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->headers !== null) {
            $data['headers'] = $this->headers;
        }

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        if ($this->links !== null) {
            $data['links'] = $this->links;
        }

        return $data;
    }
}
