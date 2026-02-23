<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Response implements JsonSerializable
{
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?Headers $headers = null,
        public ?Content $content = null,
        public ?Links $links = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        if (null !== $this->ref) {
            $data = ['$ref' => $this->ref];

            if (null !== $this->refSummary) {
                $data['summary'] = $this->refSummary;
            }

            if (null !== $this->refDescription) {
                $data['description'] = $this->refDescription;
            }

            return $data;
        }

        $data = [];

        if (null !== $this->summary) {
            $data['summary'] = $this->summary;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->headers) {
            $data['headers'] = $this->headers;
        }

        if (null !== $this->content) {
            $data['content'] = $this->content;
        }

        if (null !== $this->links) {
            $data['links'] = $this->links;
        }

        return $data;
    }
}
