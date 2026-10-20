<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

final class InvalidNumericStringException extends InvalidArgumentException
{
    use SanitizableExceptionTrait;
}
