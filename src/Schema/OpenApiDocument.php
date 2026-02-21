<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema;

use JsonSerializable;
use Override;

readonly class OpenApiDocument implements JsonSerializable
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
        public ?string $self = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'openapi' => $this->openapi,
            'info' => $this->info,
        ];

        if (null !== $this->jsonSchemaDialect) {
            $data['jsonSchemaDialect'] = $this->jsonSchemaDialect;
        }

        if (null !== $this->servers) {
            $data['servers'] = $this->servers;
        }

        if (null !== $this->paths) {
            $data['paths'] = $this->paths;
        }

        if (null !== $this->webhooks) {
            $data['webhooks'] = $this->webhooks;
        }

        if (null !== $this->components) {
            $data['components'] = $this->components;
        }

        if (null !== $this->security) {
            $data['security'] = $this->security;
        }

        if (null !== $this->tags) {
            $data['tags'] = $this->tags;
        }

        if (null !== $this->externalDocs) {
            $data['externalDocs'] = $this->externalDocs;
        }

        if (null !== $this->self) {
            $data['$self'] = $this->self;
        }

        return $data;
    }
}
