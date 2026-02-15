<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Paths implements JsonSerializable
{
    /**
     * @param array<string, PathItem> $paths
     */
    public function __construct(
        public array $paths,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->paths as $key => $pathItem) {
            $data[$key] = $pathItem->jsonSerialize();
        }
        return $data;
    }
}
