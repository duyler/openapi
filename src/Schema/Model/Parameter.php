<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Parameter implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     */
    public function __construct(
        public string $name,
        public string $in,
        public ?string $description = null,
        public bool $required = false,
        public bool $deprecated = false,
        public bool $allowEmptyValue = false,
        public ?string $style = null,
        public bool $explode = false,
        public bool $allowReserved = false,
        public ?Schema $schema = null,
        public ?array $examples = null,
        public ?Example $example = null,
        public ?Content $content = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
            'in' => $this->in,
        ];

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

        if ($this->style !== null) {
            $data['style'] = $this->style;
        }

        if ($this->explode) {
            $data['explode'] = $this->explode;
        }

        if ($this->allowReserved) {
            $data['allowReserved'] = $this->allowReserved;
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
