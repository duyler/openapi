<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback\Exception;

use InvalidArgumentException;

use function sprintf;

final class UnknownCallbackException extends InvalidArgumentException
{
    public function __construct(string $callbackName)
    {
        parent::__construct(
            sprintf('Unknown callback: %s', $callbackName),
        );
    }
}
