<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Override;

use function is_string;

final readonly class RelativeJsonPointerValidator implements FormatValidatorInterface
{
    private const string RELATIVE_POINTER_PATTERN = '/^(0|[1-9]\d*)(#|\/(\/(?:[^~\/]|~0|~1)*)*)?$/';

    #[Override]
    public function validate(mixed $data): void
    {
        if (false === is_string($data)) {
            throw new InvalidFormatException('relative-json-pointer', $data, 'Value must be a string');
        }

        if (1 !== preg_match(self::RELATIVE_POINTER_PATTERN, $data)) {
            throw new InvalidFormatException('relative-json-pointer', $data, 'Invalid Relative JSON Pointer format');
        }
    }
}
