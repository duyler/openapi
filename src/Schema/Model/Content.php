<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Content implements JsonSerializable
{
    /**
     * @param array<string, MediaType> $mediaTypes
     */
    public function __construct(
        public array $mediaTypes,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->mediaTypes as $key => $mediaType) {
            $data[$key] = $mediaType->jsonSerialize();
        }
        return $data;
    }
}
