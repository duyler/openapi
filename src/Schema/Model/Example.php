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
        public mixed $dataValue = null,
        public mixed $serializedValue = null,
        public ?string $externalValue = null,
        public ?string $serializedExample = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->summary) {
            $data['summary'] = $this->summary;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->value) {
            $data['value'] = $this->value;
        }

        if (null !== $this->dataValue) {
            $data['dataValue'] = $this->dataValue;
        }

        if (null !== $this->serializedValue) {
            $data['serializedValue'] = $this->serializedValue;
        }

        if (null !== $this->externalValue) {
            $data['externalValue'] = $this->externalValue;
        }

        if (null !== $this->serializedExample) {
            $data['serializedExample'] = $this->serializedExample;
        }

        return $data;
    }
}
