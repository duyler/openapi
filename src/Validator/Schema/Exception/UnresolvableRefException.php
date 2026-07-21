<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;
use Throwable;

final class UnresolvableRefException extends RuntimeException
{
    use SanitizableExceptionTrait;

    /**
     * Maximum retained length of attacker-controlled ref strings and
     * internal navigation traces. Excess bytes are truncated in the
     * constructor via LogContextSanitizer so a single exception cannot
     * amplify a multi-megabyte payload into logs or error trackers.
     */
    private const int MAX_REF_LENGTH_IN_EXCEPTION = 256;

    protected readonly string $ref;

    protected readonly string $reason;

    protected readonly ?string $internalTrace;

    public function __construct(
        string $ref,
        string $reason,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $internalTrace = null,
    ) {
        $this->ref = LogContextSanitizer::truncate($ref, self::MAX_REF_LENGTH_IN_EXCEPTION);
        $this->reason = LogContextSanitizer::truncate($reason, self::MAX_REF_LENGTH_IN_EXCEPTION);
        $this->internalTrace = null === $internalTrace
            ? null
            : LogContextSanitizer::truncate($internalTrace, self::MAX_REF_LENGTH_IN_EXCEPTION);

        parent::__construct(
            $this->reason,
            $code,
            $previous,
        );
    }

    /**
     * Returns the retained $ref string. Pass $reveal = true only from
     * trusted operator code; the default returns '<redacted>' to prevent
     * disclosure of internal specification structure through reflective
     * serialization.
     */
    public function ref(bool $reveal = false): string
    {
        return $reveal ? $this->ref : '<redacted>';
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * Returns the internal navigation trace (e.g. the chain of visited
     * $ref pointers). Pass $reveal = true only from trusted operator
     * code; the default returns '<redacted>' to prevent disclosure of
     * internal specification structure through reflective serialization.
     */
    public function internalTrace(bool $reveal = false): ?string
    {
        return $reveal ? $this->internalTrace : '<redacted>';
    }
}
