<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

/**
 * Thrown when a single streamed line (NDJSON, SSE) or record (JSON Text
 * Sequence) exceeds the configured size cap while reading a response body.
 * Defends against memory exhaustion from attacker-controlled streaming
 * responses that never terminate a record.
 */
final class StreamRecordTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly int $maxBytes,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forStreamLine(int $maxBytes): self
    {
        return new self(
            $maxBytes,
            sprintf(
                'Stream line exceeds maximum allowed length of %d bytes',
                $maxBytes,
            ),
        );
    }

    public static function forJsonSequenceRecord(int $maxBytes): self
    {
        return new self(
            $maxBytes,
            sprintf(
                'JSON sequence record exceeds maximum allowed length of %d bytes',
                $maxBytes,
            ),
        );
    }
}
