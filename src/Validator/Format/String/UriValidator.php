<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

final readonly class UriValidator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('uri', $data, 'Value must be a string');
        }

        $filtered = filter_var($data, FILTER_VALIDATE_URL);

        if (false === $filtered) {
            throw new InvalidFormatException('uri', $data, 'Invalid URI format');
        }
    }
}
