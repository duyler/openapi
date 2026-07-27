<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Override;

/**
 * Prevents CWE-209 / CWE-497 leakage: overrides Exception::__toString() to return only
 * the message, suppressing class name, file path, and stack trace in (string) $e casts.
 *
 * @psalm-external-mutation-free
 */
trait SanitizableExceptionTrait
{
    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
