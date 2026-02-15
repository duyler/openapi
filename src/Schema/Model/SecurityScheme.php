<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class SecurityScheme implements JsonSerializable
{
    public function __construct(
        public string $type,
        public ?string $description = null,
        public ?string $name = null,
        public ?string $in = null,
        public ?string $scheme = null,
        public ?string $bearerFormat = null,
        public ?string $flows = null,
        public ?string $authorizationUrl = null,
        public ?string $tokenUrl = null,
        public ?string $refreshUrl = null,
        public ?array $scopes = null,
        public ?string $openIdConnectUrl = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->in !== null) {
            $data['in'] = $this->in;
        }

        if ($this->scheme !== null) {
            $data['scheme'] = $this->scheme;
        }

        if ($this->bearerFormat !== null) {
            $data['bearerFormat'] = $this->bearerFormat;
        }

        if ($this->flows !== null) {
            $data['flows'] = $this->flows;
        }

        if ($this->authorizationUrl !== null) {
            $data['authorizationUrl'] = $this->authorizationUrl;
        }

        if ($this->tokenUrl !== null) {
            $data['tokenUrl'] = $this->tokenUrl;
        }

        if ($this->refreshUrl !== null) {
            $data['refreshUrl'] = $this->refreshUrl;
        }

        if ($this->scopes !== null) {
            $data['scopes'] = $this->scopes;
        }

        if ($this->openIdConnectUrl !== null) {
            $data['openIdConnectUrl'] = $this->openIdConnectUrl;
        }

        return $data;
    }
}
