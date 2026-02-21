<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Operation implements JsonSerializable
{
    /**
     * @param list<string>|null $tags
     * @param Parameters|null $parameters
     * @param SecurityRequirement|null $security
     * @param Servers|null $servers
     */
    public function __construct(
        public ?array $tags = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?ExternalDocs $externalDocs = null,
        public ?string $operationId = null,
        public ?Parameters $parameters = null,
        public ?RequestBody $requestBody = null,
        public ?Responses $responses = null,
        public ?Callbacks $callbacks = null,
        public bool $deprecated = false,
        public ?SecurityRequirement $security = null,
        public ?Servers $servers = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->tags) {
            $data['tags'] = $this->tags;
        }

        if (null !== $this->summary) {
            $data['summary'] = $this->summary;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->externalDocs) {
            $data['externalDocs'] = $this->externalDocs;
        }

        if (null !== $this->operationId) {
            $data['operationId'] = $this->operationId;
        }

        if (null !== $this->parameters) {
            $data['parameters'] = $this->parameters;
        }

        if (null !== $this->requestBody) {
            $data['requestBody'] = $this->requestBody;
        }

        if (null !== $this->responses) {
            $data['responses'] = $this->responses;
        }

        if (null !== $this->callbacks) {
            $data['callbacks'] = $this->callbacks;
        }

        if ($this->deprecated) {
            $data['deprecated'] = $this->deprecated;
        }

        if (null !== $this->security) {
            $data['security'] = $this->security;
        }

        if (null !== $this->servers) {
            $data['servers'] = $this->servers;
        }

        return $data;
    }
}
