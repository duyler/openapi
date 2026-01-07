<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Link implements JsonSerializable
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

        if ($this->operationRef !== null) {
            $data['operationRef'] = $this->operationRef;
        }

        if ($this->ref !== null) {
            $data['$ref'] = $this->ref;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
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

        if ($this->server !== null) {
            $data['server'] = $this->server;
        }

        return $data;
    }
}
