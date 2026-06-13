<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Schema\Model\Schema;

final readonly class CoercionContext
{
    public function __construct(
        public readonly ?Schema $schema,
        public readonly bool $enabled,
        public readonly bool $strict = false,
        public readonly bool $nullableAsType = true,
    ) {}
}
