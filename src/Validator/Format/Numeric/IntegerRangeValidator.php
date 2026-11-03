<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function floor;
use function is_float;
use function is_int;
use function sprintf;

final readonly class IntegerRangeValidator implements FormatValidatorInterface
{
    public function __construct(
        private readonly string $formatName,
        private readonly int $min,
        private readonly int $max,
    ) {}

    #[Override]
    public function validate(mixed $data): void
    {
        if (is_int($data)) {
            if ($data < $this->min || $data > $this->max) {
                throw new InvalidFormatException(
                    $this->formatName,
                    $data,
                    sprintf('Integer out of %s range [%d, %d]', $this->formatName, $this->min, $this->max),
                );
            }

            return;
        }

        if (is_float($data)) {
            if (floor($data) !== $data) {
                throw new InvalidFormatException(
                    $this->formatName,
                    $data,
                    sprintf('%s requires an integer value, got float %s', $this->formatName, $data),
                );
            }

            if ($data < (float) $this->min || $data > (float) $this->max) {
                throw new InvalidFormatException(
                    $this->formatName,
                    $data,
                    sprintf('Integer out of %s range [%d, %d]', $this->formatName, $this->min, $this->max),
                );
            }
        }
    }
}
