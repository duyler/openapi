<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class MinLengthError extends AbstractValidationError
{
    public function __construct(
        int $minLength,
        int $actualLength,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Value length %d is less than minimum %d at %s', $actualLength, $minLength, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'minLength',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['minLength' => $minLength, 'actual' => $actualLength],
            suggestion: sprintf('Ensure value has at least %d characters', $minLength),
        );
    }
}
