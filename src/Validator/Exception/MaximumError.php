<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class MaximumError extends AbstractValidationError
{
    public function __construct(
        float $maximum,
        float $actual,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Value %f exceeds maximum %f at %s', $actual, $maximum, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'maximum',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['maximum' => $maximum, 'actual' => $actual],
            suggestion: sprintf('Ensure value is at most %f', $maximum),
        );
    }
}
