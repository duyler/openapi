<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class PatternMismatchError extends AbstractValidationError
{
    public function __construct(
        string $pattern,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Value does not match pattern "%s" at %s', $pattern, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'pattern',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['pattern' => $pattern],
            suggestion: sprintf('Ensure value matches the pattern: %s', $pattern),
        );
    }
}
