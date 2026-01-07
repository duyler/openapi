<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Contact implements JsonSerializable
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

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        return $data;
    }
}
