<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Webhooks implements JsonSerializable
{
    /**
     * @param array<string, PathItem> $webhooks
     */
    public function __construct(
        public array $webhooks,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->webhooks as $key => $webhook) {
            $data[$key] = $webhook->jsonSerialize();
        }
        return $data;
    }
}
