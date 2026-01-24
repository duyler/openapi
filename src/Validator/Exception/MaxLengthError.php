<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class MaxLengthError extends AbstractValidationError
{
    public function __construct(
        int $maxLength,
        int $actualLength,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Value length %d exceeds maximum %d at %s', $actualLength, $maxLength, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'maxLength',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['maxLength' => $maxLength, 'actual' => $actualLength],
            suggestion: sprintf('Ensure value has at most %d characters', $maxLength),
        );
    }
}
