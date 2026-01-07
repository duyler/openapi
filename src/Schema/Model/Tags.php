<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Tags implements JsonSerializable
{
    /**
     * @param list<Tag> $tags
     */
    public function __construct(
        public array $tags,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        return ['tags' => array_map(fn($tag) => $tag->jsonSerialize(), $this->tags)];
    }
}
