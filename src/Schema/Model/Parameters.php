<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use JsonSerializable;
use Override;

readonly class Parameters implements JsonSerializable
{
    /**
     * @param list<Parameter|string> $parameters
     */
    public function __construct(
        public array $parameters,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        return ['parameters' => array_map(fn($param) => $param instanceof JsonSerializable ? $param->jsonSerialize() : $param, $this->parameters)];
    }
}
