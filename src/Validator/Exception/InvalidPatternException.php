<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class InvalidPatternException extends RuntimeException
{
    public function __construct(
        string $pattern,
        string $reason,
    ) {
        parent::__construct(
            sprintf('Invalid regex pattern "%s": %s', $pattern, $reason),
        );
    }
}
