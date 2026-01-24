<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class MinItemsError extends AbstractValidationError
{
    public function __construct(
        int $minItems,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Array has %d items, but minimum is %d at %s', $actualCount, $minItems, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'minItems',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['minItems' => $minItems, 'actual' => $actualCount],
            suggestion: sprintf('Ensure array has at least %d items', $minItems),
        );
    }
}
