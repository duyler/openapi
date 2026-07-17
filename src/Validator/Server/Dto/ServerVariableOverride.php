<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server\Dto;

final readonly class ServerVariableOverride
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
