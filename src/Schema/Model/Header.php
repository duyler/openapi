<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Header implements JsonSerializable
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

        if ($this->description !== null) {
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

        if ($this->schema !== null) {
            $data['schema'] = $this->schema;
        }

        if ($this->example !== null) {
            $data['example'] = $this->example;
        }

        if ($this->examples !== null) {
            $data['examples'] = $this->examples;
        }

        if ($this->content !== null) {
            $data['content'] = $this->content;
        }

        return $data;
    }
}
