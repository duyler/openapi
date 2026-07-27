<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

/**
 * Carries the float-path inputs for ScalarConstraints::buildFloatQuotient
 * so the collaborator method signature stays at one parameter (§10 rule of three).
 */
final readonly class FloatQuotientContext
{
    public function __construct(
        public string $multipleOfStr,
        public string $epsilonStr,
        public string $exportedMessage,
        public string $valueVar,
    ) {}
}
