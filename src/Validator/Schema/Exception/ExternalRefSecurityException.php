<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown when an external $ref violates the builtin FileExternalRefResolver
 * security policy (denied scheme, path traversal outside the allowed root).
 */
final class ExternalRefSecurityException extends RuntimeException
{
    public function __construct(
        public readonly string $ref,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
