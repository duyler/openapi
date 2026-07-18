<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Exception;

use RuntimeException;

use function sprintf;

/**
 * Thrown when a streaming content parser (NDJSON, SSE, JSON Text Sequence)
 * accumulates more records than the configured maximum. Defends against
 * memory exhaustion from attacker-controlled streaming responses that
 * consist of a very large number of small records.
 */
final class TooManyRecordsException extends RuntimeException
{
    public function __construct(
        public readonly int $max,
    ) {
        parent::__construct(
            sprintf(
                'Streaming content exceeds maximum record count: %d',
                $max,
            ),
        );
    }
}
