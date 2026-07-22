<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class BodyTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly int $actualBytes,
        public readonly int $maxBytes,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'HTTP body of %d bytes exceeds the configured maximum of %d bytes',
                $actualBytes,
                $maxBytes,
            ),
            $code,
            $previous,
        );
    }
}
