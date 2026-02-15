<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class PathItem implements JsonSerializable
{
    public function __construct(
        public ?string $ref = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?Operation $get = null,
        public ?Operation $put = null,
        public ?Operation $post = null,
        public ?Operation $delete = null,
        public ?Operation $options = null,
        public ?Operation $head = null,
        public ?Operation $patch = null,
        public ?Operation $trace = null,
        public ?Servers $servers = null,
        public ?Parameters $parameters = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->ref !== null) {
            $data['$ref'] = $this->ref;
        }

        if ($this->summary !== null) {
            $data['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->get !== null) {
            $data['get'] = $this->get;
        }

        if ($this->put !== null) {
            $data['put'] = $this->put;
        }

        if ($this->post !== null) {
            $data['post'] = $this->post;
        }

        if ($this->delete !== null) {
            $data['delete'] = $this->delete;
        }

        if ($this->options !== null) {
            $data['options'] = $this->options;
        }

        if ($this->head !== null) {
            $data['head'] = $this->head;
        }

        if ($this->patch !== null) {
            $data['patch'] = $this->patch;
        }

        if ($this->trace !== null) {
            $data['trace'] = $this->trace;
        }

        if ($this->servers !== null) {
            $data['servers'] = $this->servers;
        }

        if ($this->parameters !== null) {
            $data['parameters'] = $this->parameters;
        }

        return $data;
    }
}
