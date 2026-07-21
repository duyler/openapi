<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

/**
 * Thrown when an external $ref file exceeds the configured maximum size
 * (FileExternalRefResolver::$maxBytes). Semantically a size / DoS error,
 * not a security policy violation, so it extends RuntimeException directly
 * rather than ExternalRefSecurityException.
 */
final class ExternalRefTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(public readonly int $max)
    {
        parent::__construct(sprintf(
            'External ref file exceeds maximum allowed size of %d bytes',
            $max,
        ));
    }
}
