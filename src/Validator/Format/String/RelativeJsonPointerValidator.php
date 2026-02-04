<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;

use function preg_match;

final readonly class RelativeJsonPointerValidator extends AbstractStringFormatValidator
{
    private const string RELATIVE_POINTER_PATTERN = '/^(0|[1-9]\d*)(#|\/(\/(?:[^~\/]|~0|~1)*)*)?$/';

    #[Override]
    protected function getFormatName(): string
    {
        return 'relative-json-pointer';
    }

    #[Override]
    protected function validateString(string $data): void
    {
        if (1 !== preg_match(self::RELATIVE_POINTER_PATTERN, $data)) {
            throw new InvalidFormatException('relative-json-pointer', $data, 'Invalid Relative JSON Pointer format');
        }
    }
}
