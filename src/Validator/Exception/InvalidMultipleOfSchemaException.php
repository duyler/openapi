<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Deprecated;
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

    #[Deprecated(
        message: 'since R4-CORRECTNESS-008: NumericRangeValidator::isMultipleOf now '
            . 'uses pure-PHP decimal string modulus when bcmath is unavailable, '
            . 'so this factory is no longer thrown from the validator. Retained '
            . 'for backward compatibility with external callers; will be removed '
            . 'in 2.0.',
    )]
    public static function forLargeIntegerWithoutBcmath(int $data, float $multipleOf): self
    {
        return new self(sprintf(
            'Cannot reliably check multipleOf for integer %d with float multipleOf %s without BCMath extension',
            $data,
            $multipleOf,
        ));
    }
}
