<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use RuntimeException;
use Throwable;

final class ServerUrlMismatchException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
