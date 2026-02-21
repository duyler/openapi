<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Contact implements JsonSerializable
{
    public function __construct(
        public ?string $name = null,
        public ?string $url = null,
        public ?string $email = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];

        if (null !== $this->name) {
            $data['name'] = $this->name;
        }

        if (null !== $this->url) {
            $data['url'] = $this->url;
        }

        if (null !== $this->email) {
            $data['email'] = $this->email;
        }

        return $data;
    }
}
