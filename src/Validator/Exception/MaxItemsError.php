<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

class MaxItemsError extends AbstractValidationError
{
    public function __construct(
        int $maxItems,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Array has %d items, but maximum is %d at %s', $actualCount, $maxItems, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'maxItems',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['maxItems' => $maxItems, 'actual' => $actualCount],
            suggestion: sprintf('Ensure array has at most %d items', $maxItems),
        );
    }
}
