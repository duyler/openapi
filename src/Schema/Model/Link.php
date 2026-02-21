<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Link implements JsonSerializable
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public ?string $operationRef = null,
        public ?string $ref = null,
        public ?string $description = null,
        public ?string $operationId = null,
        public ?array $parameters = null,
        public ?RequestBody $requestBody = null,
        public ?Server $server = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->operationRef) {
            $data['operationRef'] = $this->operationRef;
        }

        if (null !== $this->ref) {
            $data['$ref'] = $this->ref;
        }

        if (null !== $this->description) {
            $data['description'] = $this->description;
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

        if (null !== $this->server) {
            $data['server'] = $this->server;
        }

        return $data;
    }
}
