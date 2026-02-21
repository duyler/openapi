<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Components implements JsonSerializable
{
    /**
     * @param array<string, Schema>|null $schemas
     * @param array<string, Response>|null $responses
     * @param array<string, Parameter>|null $parameters
     * @param array<string, Example>|null $examples
     * @param array<string, RequestBody>|null $requestBodies
     * @param array<string, Header>|null $headers
     * @param array<string, SecurityScheme>|null $securitySchemes
     * @param array<string, Link>|null $links
     * @param array<string, Callbacks>|null $callbacks
     * @param array<string, PathItem>|null $pathItems
     * @param array<string, MediaType>|null $mediaTypes
     */
    public function __construct(
        public ?array $schemas = null,
        public ?array $responses = null,
        public ?array $parameters = null,
        public ?array $examples = null,
        public ?array $requestBodies = null,
        public ?array $headers = null,
        public ?array $securitySchemes = null,
        public ?array $links = null,
        public ?array $callbacks = null,
        public ?array $pathItems = null,
        public ?array $mediaTypes = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->schemas) {
            $data['schemas'] = $this->schemas;
        }

        if (null !== $this->responses) {
            $data['responses'] = $this->responses;
        }

        if (null !== $this->parameters) {
            $data['parameters'] = $this->parameters;
        }

        if (null !== $this->examples) {
            $data['examples'] = $this->examples;
        }

        if (null !== $this->requestBodies) {
            $data['requestBodies'] = $this->requestBodies;
        }

        if (null !== $this->headers) {
            $data['headers'] = $this->headers;
        }

        if (null !== $this->securitySchemes) {
            $data['securitySchemes'] = $this->securitySchemes;
        }

        if (null !== $this->links) {
            $data['links'] = $this->links;
        }

        if (null !== $this->callbacks) {
            $data['callbacks'] = $this->callbacks;
        }

        if (null !== $this->pathItems) {
            $data['pathItems'] = $this->pathItems;
        }

        if (null !== $this->mediaTypes) {
            $data['mediaTypes'] = $this->mediaTypes;
        }

        return $data;
    }
}
