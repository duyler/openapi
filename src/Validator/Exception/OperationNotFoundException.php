<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;
use function strtoupper;

final class OperationNotFoundException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly string $requestPath,
        public readonly string $method,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Operation not found: %s %s', strtoupper($method), $requestPath),
            $code,
            $previous,
        );
    }
}
