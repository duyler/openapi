<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;
use Throwable;

/**
 * Thrown when an external $ref violates the builtin FileExternalRefResolver
 * security policy (denied scheme, path traversal outside the allowed root).
 */
final class ExternalRefSecurityException extends RuntimeException
{
    use SanitizableExceptionTrait;

    /**
     * Maximum retained length of attacker-controlled ref strings. Excess
     * bytes are truncated in the constructor via LogContextSanitizer so
     * a single exception cannot amplify a multi-megabyte payload.
     */
    private const int MAX_REF_LENGTH_IN_EXCEPTION = 256;

    protected readonly string $ref;

    public function __construct(
        string $ref,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $this->ref = LogContextSanitizer::truncate($ref, self::MAX_REF_LENGTH_IN_EXCEPTION);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the retained $ref string. Pass $reveal = true only from
     * trusted operator code (security auditor, verbose logger); the
     * default returns '<redacted>' to prevent disclosure through
     * reflective serialization ((array) $e, Sentry SDK, etc.).
     */
    public function ref(bool $reveal = false): string
    {
        return $reveal ? $this->ref : '<redacted>';
    }
}
