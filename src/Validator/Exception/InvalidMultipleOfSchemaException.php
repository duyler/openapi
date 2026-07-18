<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;

use function sprintf;

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

    public static function forLargeIntegerWithoutBcmath(int $data, float $multipleOf): self
    {
        return new self(sprintf(
            'Cannot reliably check multipleOf for integer %d with float multipleOf %s without BCMath extension',
            $data,
            $multipleOf,
        ));
    }
}
