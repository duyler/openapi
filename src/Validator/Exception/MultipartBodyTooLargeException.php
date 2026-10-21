<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

final class MultipartBodyTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(int $maxParts)
    {
        parent::__construct(
            sprintf('Maximum multipart parts of %d exceeded', $maxParts),
        );
    }
}
