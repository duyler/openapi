<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class ExternalDocs implements JsonSerializable
{
    public function __construct(
        public string $url,
        public ?string $description = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [
            'url' => $this->url,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $data;
    }
}
