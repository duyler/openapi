<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class MultipartNestingDepthException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public static function limitReached(int $maxDepth): self
    {
        return new self(
            sprintf('Maximum multipart nesting depth of %d exceeded', $maxDepth),
        );
    }
}
