<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Override;

/**
 * Trait for exception classes that should not leak server paths, stack
 * traces, or internal context through the default Exception::__toString().
 *
 * Apply to every exception class in the package that does not already
 * override __toString(). The default Exception::__toString() returns the
 * class name, file path, and full stack trace, which is a CWE-209 / CWE-497
 * information disclosure when the exception reaches PSR-15 middleware or
 * PSR-3 logs through (string) $e casts.
 *
 * This trait does not affect programmatic access to properties; callers
 * that legitimately need access to sensitive fields must use explicit
 * opt-in getters (e.g. $e->value(reveal: true)) on classes that expose them.
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
