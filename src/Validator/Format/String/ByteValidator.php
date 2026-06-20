<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function base64_decode;
use function base64_encode;
use function strtr;

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

        if (false !== $decoded && base64_encode($decoded) === $data) {
            return;
        }

        $urlSafeDecoded = base64_decode(strtr($data, '-_', '+/'), true);

        if (false !== $urlSafeDecoded && strtr(base64_encode($urlSafeDecoded), '+/', '-_') === $data) {
            return;
        }

        throw new InvalidFormatException('byte', $data, 'Invalid base64 encoding (standard or url-safe)');
    }
}
