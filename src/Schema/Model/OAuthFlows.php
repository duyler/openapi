<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class OAuthFlows implements JsonSerializable
{
    public function __construct(
        public ?OAuthFlow $implicit = null,
        public ?OAuthFlow $password = null,
        public ?OAuthFlow $clientCredentials = null,
        public ?OAuthFlow $authorizationCode = null,
        public ?OAuthFlow $deviceCode = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->implicit) {
            $data['implicit'] = $this->implicit->jsonSerialize();
        }

        if (null !== $this->password) {
            $data['password'] = $this->password->jsonSerialize();
        }

        if (null !== $this->clientCredentials) {
            $data['clientCredentials'] = $this->clientCredentials->jsonSerialize();
        }

        if (null !== $this->authorizationCode) {
            $data['authorizationCode'] = $this->authorizationCode->jsonSerialize();
        }

        if (null !== $this->deviceCode) {
            $data['deviceCode'] = $this->deviceCode->jsonSerialize();
        }

        return $data;
    }
}
