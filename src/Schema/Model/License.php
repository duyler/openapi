<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class License implements JsonSerializable
{
    public function __construct(
        public string $name,
        public ?string $identifier = null,
        public ?string $url = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
        ];

        if (null !== $this->identifier) {
            $data['identifier'] = $this->identifier;
        }

        if (null !== $this->url) {
            $data['url'] = $this->url;
        }

        return $data;
    }
}
