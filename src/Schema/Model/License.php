<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class License implements JsonSerializable
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

        if ($this->identifier !== null) {
            $data['identifier'] = $this->identifier;
        }

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        return $data;
    }
}
