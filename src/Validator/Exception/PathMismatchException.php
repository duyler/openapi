<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

class PathMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $template,
        public readonly string $requestPath,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Path "%s" does not match template "%s"', $requestPath, $template),
            $code,
            $previous,
        );
    }
}
