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
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->schemas !== null) {
            $data['schemas'] = $this->schemas;
        }

        if ($this->responses !== null) {
            $data['responses'] = $this->responses;
        }

        if ($this->parameters !== null) {
            $data['parameters'] = $this->parameters;
        }

        if ($this->examples !== null) {
            $data['examples'] = $this->examples;
        }

        if ($this->requestBodies !== null) {
            $data['requestBodies'] = $this->requestBodies;
        }

        if ($this->headers !== null) {
            $data['headers'] = $this->headers;
        }

        if ($this->securitySchemes !== null) {
            $data['securitySchemes'] = $this->securitySchemes;
        }

        if ($this->links !== null) {
            $data['links'] = $this->links;
        }

        if ($this->callbacks !== null) {
            $data['callbacks'] = $this->callbacks;
        }

        if ($this->pathItems !== null) {
            $data['pathItems'] = $this->pathItems;
        }

        return $data;
    }
}
