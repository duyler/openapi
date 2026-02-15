<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

use function sprintf;

final class UndefinedResponseException extends RuntimeException
{
    /**
     * @param list<string> $definedResponses
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $definedResponses,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Undefined response status code "%d". Defined responses: %s',
                $statusCode,
                implode(', ', $definedResponses),
            ),
            $code,
            $previous,
        );
    }
}
