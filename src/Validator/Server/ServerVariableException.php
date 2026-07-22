<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

final class ServerVariableException extends RuntimeException
{
    use SanitizableExceptionTrait;
}
