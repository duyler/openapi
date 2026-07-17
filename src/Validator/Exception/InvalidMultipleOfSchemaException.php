<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * Thrown when a Schema defines multipleOf with a non-positive value, or when
 * the validator cannot reliably verify multipleOf for a large integer without
 * the bcmath extension.
 *
 * The OpenAPI specification forbids multipleOf values that are zero or negative,
 * because such values make the constraint mathematically meaningless.
 */
final class InvalidMultipleOfSchemaException extends InvalidArgumentException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forNonPositiveValue(float $multipleOf): self
    {
        return new self(sprintf(
            'Schema multipleOf must be a positive number, got %f.',
            $multipleOf,
        ));
    }

    /**
     * Factory for the case where integer data combined with a non-integer
     * multipleOf exceeds the precision that the float relative-epsilon
     * check can guarantee without the bcmath extension. Fail-closed to
     * avoid silently accepting arbitrary large values.
     */
    public static function forLargeIntegerWithoutBcmath(int $data, float $multipleOf): self
    {
        return new self(sprintf(
            'Cannot reliably check multipleOf for integer %d with float multipleOf %s without BCMath extension',
            $data,
            $multipleOf,
        ));
    }
}
