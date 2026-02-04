<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use const FILTER_VALIDATE_URL;

final readonly class UriValidator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'uri';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $filtered = filter_var($data, FILTER_VALIDATE_URL);

        if (false === $filtered) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }
    }
}
