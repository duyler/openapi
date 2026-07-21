<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

/**
 * Thrown when a PCRE function reports a non-zero error via preg_last_error().
 *
 * Such errors indicate that PCRE hit an internal limit (backtrack_limit,
 * recursion_limit) or encountered a JIT stack overflow, and the return value
 * of preg_match / preg_match_all cannot be trusted as a definitive answer.
 * Validation cannot proceed reliably in this state, so the PregExecutor
 * surfaces the failure rather than silently treating it as "no match".
 */
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
