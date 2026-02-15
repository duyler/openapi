<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Callbacks implements JsonSerializable
{
    /**
     * @param array<string, array<string, PathItem>> $callbacks
     */
    public function __construct(
        public array $callbacks,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        $data = [];
        foreach ($this->callbacks as $key => $callback) {
            $data[$key] = [];
            foreach ($callback as $expression => $pathItem) {
                $data[$key][$expression] = $pathItem->jsonSerialize();
            }
        }
        return $data;
    }
}
