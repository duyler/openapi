<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Internal;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;

/**
 * @internal
 */
final readonly class ValidationContextInit
{
    public function __construct(
        public readonly ValidatorPool $pool,
        public readonly ?ErrorFormatterInterface $errorFormatter = null,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly ?ValidatorMode $mode = null,
    ) {}
}
