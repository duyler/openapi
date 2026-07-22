<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

final class CompilationCacheException extends RuntimeException
{
    use SanitizableExceptionTrait;
}
