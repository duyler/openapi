<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

final readonly class EmailValidator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('email', $data, 'Value must be a string');
        }

        $filtered = filter_var($data, FILTER_VALIDATE_EMAIL);

        if (false === $filtered) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }
}
