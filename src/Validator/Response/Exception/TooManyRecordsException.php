<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

final class TooManyRecordsException extends RuntimeException
{
    use SanitizableExceptionTrait;

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
