<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

final class InvalidUtf8Exception extends RuntimeException
{
    use SanitizableExceptionTrait;
}
