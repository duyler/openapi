<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema;

use JsonSerializable;
use Override;

final readonly class OpenApiDocument implements JsonSerializable
{
    public function __construct(
        public string $openapi,
        public Model\InfoObject $info,
        public ?string $jsonSchemaDialect = null,
        public ?Model\Servers $servers = null,
        public ?Model\Paths $paths = null,
        public ?Model\Webhooks $webhooks = null,
        public ?Model\Components $components = null,
        public ?Model\SecurityRequirement $security = null,
        public ?Model\Tags $tags = null,
        public ?Model\ExternalDocs $externalDocs = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'openapi' => $this->openapi,
            'info' => $this->info,
        ];

        if ($this->jsonSchemaDialect !== null) {
            $data['jsonSchemaDialect'] = $this->jsonSchemaDialect;
        }

        if ($this->servers !== null) {
            $data['servers'] = $this->servers;
        }

        if ($this->paths !== null) {
            $data['paths'] = $this->paths;
        }

        if ($this->webhooks !== null) {
            $data['webhooks'] = $this->webhooks;
        }

        if ($this->components !== null) {
            $data['components'] = $this->components;
        }

        if ($this->security !== null) {
            $data['security'] = $this->security;
        }

        if ($this->tags !== null) {
            $data['tags'] = $this->tags;
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs;
        }

        return $data;
    }
}
