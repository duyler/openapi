<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback\Exception;

use Duyler\OpenApi\Validator\Exception\SanitizableExceptionTrait;
use InvalidArgumentException;

use function sprintf;

final class UnknownCallbackException extends InvalidArgumentException
{
    use SanitizableExceptionTrait;

    public function __construct(string $callbackName)
    {
        parent::__construct(
            sprintf('Unknown callback: %s', $callbackName),
        );
    }
}
