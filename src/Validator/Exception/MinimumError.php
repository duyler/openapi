<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

class MinimumError extends AbstractValidationError
{
    public function __construct(
        float $minimum,
        float $actual,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Value %f is less than minimum %f at %s', $actual, $minimum, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'minimum',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['minimum' => $minimum, 'actual' => $actual],
            suggestion: sprintf('Ensure value is at least %f', $minimum),
        );
    }
}
