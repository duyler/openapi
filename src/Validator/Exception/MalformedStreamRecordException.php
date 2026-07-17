<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class MalformedStreamRecordException extends RuntimeException
{
    public function __construct(
        public readonly string $record,
        Throwable $previous,
        int $code = 0,
    ) {
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
