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
