<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;

final readonly class ParameterValidationConfig
{
    public function __construct(
        public bool $nullableAsType = true,
        public EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
    ) {}
}
