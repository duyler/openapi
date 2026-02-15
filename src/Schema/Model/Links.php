<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Links implements JsonSerializable
{
    /**
     * @param array<string, Link> $links
     */
    public function __construct(
        public array $links,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->links as $key => $link) {
            $data[$key] = $link->jsonSerialize();
        }
        return $data;
    }
}
