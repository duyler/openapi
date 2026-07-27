<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

use function sprintf;

final class UnknownValidatorException extends InvalidArgumentException
{
    use SanitizableExceptionTrait;

    public function __construct(string $type)
    {
        parent::__construct(sprintf('Unknown validator type: %s', $type));
    }
}
