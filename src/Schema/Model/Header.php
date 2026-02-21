<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Header implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     */
    public function __construct(
        public ?string $description = null,
        public bool $required = false,
        public bool $deprecated = false,
        public bool $allowEmptyValue = false,
        public ?Schema $schema = null,
        public mixed $example = null,
        public ?array $examples = null,
        public ?Content $content = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if ($this->required) {
            $data['required'] = $this->required;
        }

        if ($this->deprecated) {
            $data['deprecated'] = $this->deprecated;
        }

        if ($this->allowEmptyValue) {
            $data['allowEmptyValue'] = $this->allowEmptyValue;
        }

        if (null !== $this->schema) {
            $data['schema'] = $this->schema;
        }

        if (null !== $this->example) {
            $data['example'] = $this->example;
        }

        if (null !== $this->examples) {
            $data['examples'] = $this->examples;
        }

        if (null !== $this->content) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
