<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;

final readonly class JsonPointerValidator extends AbstractStringFormatValidator
{
    private const string POINTER_PATTERN = '/^(?:\/(?:[^~\/]|~0|~1)*)*$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'json-pointer';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if ($data === '' || $data === '/') {
            return;
        }

        if (1 !== preg_match(self::POINTER_PATTERN, $data)) {
            throw new InvalidFormatException('json-pointer', $data, 'Invalid JSON Pointer format');
        }
    }
}
