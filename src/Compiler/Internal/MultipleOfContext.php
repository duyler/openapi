<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

final readonly class MultipleOfContext
{
    public function __construct(
        public int $intMultipleOf,
        public string $floatMultipleOfStr,
        public string $epsilonStr,
        public string $errorMessage,
        public string $valueVar,
    ) {}
}
