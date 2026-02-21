<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Parameter implements JsonSerializable
{
    /**
     * @param array<string, mixed> $examples
     */
    public function __construct(
        public ?string $ref = null,
        public ?string $refSummary = null,
        public ?string $refDescription = null,
        public ?string $name = null,
        public ?string $in = null,
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

        if (null !== $this->name) {
            $data['name'] = $this->name;
        }

        if (null !== $this->in) {
            $data['in'] = $this->in;
        }

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

        if (null !== $this->style) {
            $data['style'] = $this->style;
        }

        if ($this->explode) {
            $data['explode'] = $this->explode;
        }

        if ($this->allowReserved) {
            $data['allowReserved'] = $this->allowReserved;
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
