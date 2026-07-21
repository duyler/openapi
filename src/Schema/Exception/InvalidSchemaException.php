<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

final class InvalidSchemaException extends RuntimeException
{
    use SanitizableExceptionTrait;
}
