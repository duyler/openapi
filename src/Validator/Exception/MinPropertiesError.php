<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class MinPropertiesError extends AbstractValidationError
{
    public function __construct(
        int $minProperties,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Object has %d properties, but minimum is %d at %s', $actualCount, $minProperties, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'minProperties',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['minProperties' => $minProperties, 'actual' => $actualCount],
            suggestion: sprintf('Ensure object has at least %d properties', $minProperties),
        );
    }
}
