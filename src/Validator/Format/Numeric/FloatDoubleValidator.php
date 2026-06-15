<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use InvalidArgumentException;

use function is_float;
use function is_infinite;
use function is_nan;

final readonly class FloatDoubleValidator implements FormatValidatorInterface
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

        // INF, -INF and NAN are float in PHP but cannot be serialized as JSON
        // per RFC 8259 §6, so they must not validate as float/double format.
        if (is_infinite($data) || is_nan($data)) {
            throw new InvalidFormatException(
                $this->format,
                $data,
                'INF and NAN are not valid JSON float values per RFC 8259 §6',
            );
        }
    }
}
