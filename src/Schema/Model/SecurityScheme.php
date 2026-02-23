<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class SecurityScheme implements JsonSerializable
{
    /**
     * @param array<string, string>|null $scopes
     */
    public function __construct(
        public string $type,
        public ?string $description = null,
        public ?string $name = null,
        public ?string $in = null,
        public ?string $scheme = null,
        public ?string $bearerFormat = null,
        public ?OAuthFlows $flows = null,
        public ?string $openIdConnectUrl = null,
        public ?string $oauth2MetadataUrl = null,
        public ?string $authorizationUrl = null,
        public ?string $tokenUrl = null,
        public ?string $refreshUrl = null,
        public ?array $scopes = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'type' => $this->type,
        ];

        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        if (null !== $this->name) {
            $data['name'] = $this->name;
        }

        if (null !== $this->in) {
            $data['in'] = $this->in;
        }

        if (null !== $this->scheme) {
            $data['scheme'] = $this->scheme;
        }

        if (null !== $this->bearerFormat) {
            $data['bearerFormat'] = $this->bearerFormat;
        }

        if (null !== $this->flows) {
            $data['flows'] = $this->flows->jsonSerialize();
        }

        if (null !== $this->openIdConnectUrl) {
            $data['openIdConnectUrl'] = $this->openIdConnectUrl;
        }

        if (null !== $this->oauth2MetadataUrl) {
            $data['oauth2MetadataUrl'] = $this->oauth2MetadataUrl;
        }

        if (null !== $this->authorizationUrl) {
            $data['authorizationUrl'] = $this->authorizationUrl;
        }

        if (null !== $this->tokenUrl) {
            $data['tokenUrl'] = $this->tokenUrl;
        }

        if (null !== $this->refreshUrl) {
            $data['refreshUrl'] = $this->refreshUrl;
        }

        if (null !== $this->scopes) {
            $data['scopes'] = $this->scopes;
        }

        return $data;
    }
}
