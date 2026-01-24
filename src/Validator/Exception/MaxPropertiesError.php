<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class MaxPropertiesError extends AbstractValidationError
{
    public function __construct(
        int $maxProperties,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Object has %d properties, but maximum is %d at %s', $actualCount, $maxProperties, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'maxProperties',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['maxProperties' => $maxProperties, 'actual' => $actualCount],
            suggestion: sprintf('Ensure object has at most %d properties', $maxProperties),
        );
    }
}
