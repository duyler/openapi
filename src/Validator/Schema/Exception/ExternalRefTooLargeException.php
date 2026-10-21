<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use RuntimeException;

use function sprintf;

final class ExternalRefTooLargeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(public readonly int $max)
    {
        parent::__construct(sprintf(
            'External ref file exceeds maximum allowed size of %d bytes',
            $max,
        ));
    }
}
