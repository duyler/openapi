<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

final class BuilderException extends RuntimeException
{
    use SanitizableExceptionTrait;
}
