<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class MultipleOfError extends AbstractValidationError
{
    public function __construct(
        int $validCount,
        int $expectedCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Data is valid against %d schemas, but should be valid against exactly one at %s', $validCount, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'multipleOf',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['validCount' => $validCount, 'expectedCount' => $expectedCount],
            suggestion: 'Ensure data matches exactly one of the schemas',
        );
    }
}
