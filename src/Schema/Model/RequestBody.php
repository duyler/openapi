<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class RequestBody implements JsonSerializable
{
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $description = null,
        public ?Content $content = null,
        public bool $required = false,
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

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->content) {
            $data['content'] = $this->content;
        }

        if ($this->required) {
            $data['required'] = $this->required;
        }

        return $data;
    }
}
