<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\RefSerializer;
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
        $refData = new RefSerializer()->toWireForm($this->ref, $this->refSummary, $this->refDescription);

        if (null !== $refData) {
            return $refData;
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
