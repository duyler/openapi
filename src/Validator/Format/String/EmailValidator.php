<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use const FILTER_VALIDATE_EMAIL;

readonly class EmailValidator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'email';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $filtered = filter_var($data, FILTER_VALIDATE_EMAIL);

        if (false === $filtered) {
            throw new InvalidFormatException('email', $data, 'Invalid email format');
        }
    }
}
