<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

use function strtolower;

final readonly class PathItem implements JsonSerializable
{
    /**
     * @param array<string, Operation>|null $additionalOperations
     */
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
        public ?Operation $query = null,
        public ?array $additionalOperations = null,
        public ?Servers $servers = null,
        public ?Parameters $parameters = null,
    ) {}

    public function getOperation(string $method): ?Operation
    {
        return match (strtolower($method)) {
            'get' => $this->get,
            'put' => $this->put,
            'post' => $this->post,
            'delete' => $this->delete,
            'options' => $this->options,
            'head' => $this->head,
            'patch' => $this->patch,
            'trace' => $this->trace,
            'query' => $this->query,
            default => null,
        };
    }

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->ref) {
            $data['$ref'] = $this->ref;
        }

        if (null !== $this->summary) {
            $data['summary'] = $this->summary;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->get) {
            $data['get'] = $this->get;
        }

        if (null !== $this->put) {
            $data['put'] = $this->put;
        }

        if (null !== $this->post) {
            $data['post'] = $this->post;
        }

        if (null !== $this->delete) {
            $data['delete'] = $this->delete;
        }

        if (null !== $this->options) {
            $data['options'] = $this->options;
        }

        if (null !== $this->head) {
            $data['head'] = $this->head;
        }

        if (null !== $this->patch) {
            $data['patch'] = $this->patch;
        }

        if (null !== $this->trace) {
            $data['trace'] = $this->trace;
        }

        if (null !== $this->query) {
            $data['query'] = $this->query;
        }

        if (null !== $this->additionalOperations) {
            $data['additionalOperations'] = $this->additionalOperations;
        }

        if (null !== $this->servers) {
            $data['servers'] = $this->servers;
        }

        if (null !== $this->parameters) {
            $data['parameters'] = $this->parameters;
        }

        return $data;
    }
}
