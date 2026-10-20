<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

final readonly class FloatQuotientContext
{
    public function __construct(
        public string $multipleOfStr,
        public string $epsilonStr,
        public string $exportedMessage,
        public string $valueVar,
    ) {}
}
