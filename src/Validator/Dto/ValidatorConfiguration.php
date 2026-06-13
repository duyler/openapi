<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;

final readonly class ValidatorConfiguration
{
    public function __construct(
        public readonly bool $coercion = false,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly bool $securityValidation = false,
        public readonly bool $strictFormats = false,
        public readonly bool $reportDeprecated = true,
    ) {}
}
