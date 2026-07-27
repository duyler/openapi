<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

/**
 * Carries the integer-path inputs for ScalarConstraints::generateMultipleOf
 * so the collaborator method signature stays at one parameter (§10 rule of three).
 */
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
