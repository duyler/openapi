<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class PregRuntimeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly int $error,
        string $message,
    ) {
        parent::__construct(
            sprintf('PCRE error %d: %s', $error, $message),
        );
    }
}
