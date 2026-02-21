<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class OAuthFlow implements JsonSerializable
{
    public function __construct(
        public ?string $authorizationUrl = null,
        public ?string $tokenUrl = null,
        public ?string $refreshUrl = null,
        public ?array $scopes = null,
        public ?string $deviceAuthorizationUrl = null,
        public ?bool $deprecated = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

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

        if (null !== $this->deviceAuthorizationUrl) {
            $data['deviceAuthorizationUrl'] = $this->deviceAuthorizationUrl;
        }

        if (null !== $this->deprecated) {
            $data['deprecated'] = $this->deprecated;
        }

        return $data;
    }
}
