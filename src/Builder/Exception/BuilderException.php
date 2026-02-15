<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Exception;

use Exception;
use Throwable;

final class BuilderException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
