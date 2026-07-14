<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

use function sprintf;

/**
 * Thrown when a Schema defines multipleOf with a non-positive value.
 *
 * The OpenAPI specification forbids multipleOf values that are zero or negative,
 * because such values make the constraint mathematically meaningless.
 */
final class InvalidMultipleOfSchemaException extends InvalidArgumentException
{
    public function __construct(float $multipleOf)
    {
        parent::__construct(sprintf(
            'Schema multipleOf must be a positive number, got %f.',
            $multipleOf,
        ));
    }
}
