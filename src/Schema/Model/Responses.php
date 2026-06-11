<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

final readonly class Responses implements JsonSerializable
{
    /**
     * @param array<string, Response> $responses
     */
    public function __construct(
        public array $responses,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->responses as $key => $response) {
            $data[$key] = $response->jsonSerialize();
        }
        return $data;
    }
}
