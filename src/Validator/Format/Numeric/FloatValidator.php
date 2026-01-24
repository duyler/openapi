<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_float;

final readonly class FloatValidator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_float($data)) {
            throw new InvalidFormatException('float', $data, 'Value must be a float');
        }
    }
}
