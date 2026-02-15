<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use InvalidArgumentException;

use function is_float;

readonly class FloatDoubleValidator implements FormatValidatorInterface
{
    private const string FLOAT = 'float';
    private const string DOUBLE = 'double';

    public function __construct(
        private readonly string $format,
    ) {
        if (self::FLOAT !== $this->format && self::DOUBLE !== $this->format) {
            throw new InvalidArgumentException('Format must be "float" or "double"');
        }
    }

    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_float($data)) {
            throw new InvalidFormatException(
                $this->format,
                $data,
                'Value must be a ' . $this->format,
            );
        }
    }
}
