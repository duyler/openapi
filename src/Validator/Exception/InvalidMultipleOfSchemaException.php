<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

use function sprintf;

final class InvalidMultipleOfSchemaException extends InvalidArgumentException
{
    use SanitizableExceptionTrait;

    public static function forNonPositiveValue(float $multipleOf): self
    {
        return new self(sprintf(
            'Schema multipleOf must be a positive number, got %f.',
            $multipleOf,
        ));
    }
}
