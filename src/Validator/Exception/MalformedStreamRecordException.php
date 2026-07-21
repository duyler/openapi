<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use RuntimeException;
use Throwable;

use function sprintf;

final class MalformedStreamRecordException extends RuntimeException
{
    use SanitizableExceptionTrait;

    /**
     * Maximum retained length of the attacker-supplied record. Excess
     * bytes are truncated in the constructor via LogContextSanitizer
     * before assignment so a single exception cannot exhaust logger
     * memory or amplify a multi-megabyte payload into PSR-3 context.
     */
    private const int MAX_RECORD_LENGTH_IN_EXCEPTION = 256;

    public readonly string $record;

    public function __construct(
        string $record,
        Throwable $previous,
        int $code = 0,
    ) {
        $this->record = LogContextSanitizer::truncate(
            $record,
            self::MAX_RECORD_LENGTH_IN_EXCEPTION,
        );

        parent::__construct(
            sprintf(
                'Malformed streaming record failed JSON decode: %s',
                $previous->getMessage(),
            ),
            $code,
            $previous,
        );
    }
}
