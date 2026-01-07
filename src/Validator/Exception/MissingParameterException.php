<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;
use Throwable;

class MissingParameterException extends RuntimeException
{
    public function __construct(
        public readonly string $location,
        public readonly string $parameterName,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Missing required parameter "%s" in %s', $parameterName, $location),
            $code,
            $previous,
        );
    }
}
