<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

final class PathMismatchException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly string $template,
        public readonly string $requestPath,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            'Request path does not match any declared template',
            $code,
            $previous,
        );
    }
}
