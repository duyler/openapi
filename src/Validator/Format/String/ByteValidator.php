<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;

final readonly class ByteValidator implements FormatValidatorInterface
{
    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('byte', $data, 'Value must be a string');
        }

        $decoded = base64_decode($data, true);

        if (false === $decoded) {
            throw new InvalidFormatException('byte', $data, 'Invalid base64 format');
        }

        if (base64_encode($decoded) !== $data) {
            throw new InvalidFormatException('byte', $data, 'Invalid base64 encoding');
        }
    }
}
