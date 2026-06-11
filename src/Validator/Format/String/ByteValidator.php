<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function base64_decode;
use function base64_encode;

final readonly class ByteValidator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'byte';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        $decoded = base64_decode($data, true);

        if (false === $decoded) {
            throw new InvalidFormatException('byte', $data, 'Invalid base64 format');
        }

        if (base64_encode($decoded) !== $data) {
            throw new InvalidFormatException('byte', $data, 'Invalid base64 encoding');
        }
    }
}
