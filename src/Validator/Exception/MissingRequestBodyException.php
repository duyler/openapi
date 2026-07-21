<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

final class MissingRequestBodyException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct()
    {
        parent::__construct('Request body is required but missing or empty');
    }
}
