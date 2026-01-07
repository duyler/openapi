<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Operation implements JsonSerializable
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

        if ($this->tags !== null) {
            $data['tags'] = $this->tags;
        }

        if ($this->summary !== null) {
            $data['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs;
        }

        if ($this->operationId !== null) {
            $data['operationId'] = $this->operationId;
        }

        if ($this->parameters !== null) {
            $data['parameters'] = $this->parameters;
        }

        if ($this->requestBody !== null) {
            $data['requestBody'] = $this->requestBody;
        }

        if ($this->responses !== null) {
            $data['responses'] = $this->responses;
        }

        if ($this->callbacks !== null) {
            $data['callbacks'] = $this->callbacks;
        }

        if ($this->deprecated) {
            $data['deprecated'] = $this->deprecated;
        }

        if ($this->security !== null) {
            $data['security'] = $this->security;
        }

        if ($this->servers !== null) {
            $data['servers'] = $this->servers;
        }

        return $data;
    }
}
