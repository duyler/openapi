<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

final class UnknownValidatorException extends InvalidArgumentException
{
    public function __construct(string $type)
    {
        parent::__construct('Unknown validator type: ' . $type);
    }
}
