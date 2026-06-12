<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Headers implements JsonSerializable
{
    /**
     * @param array<string, Header> $headers
     */
    public function __construct(
        public array $headers,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->headers as $key => $header) {
            $data[$key] = $header->jsonSerialize();
        }
        return $data;
    }
}
