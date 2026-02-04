<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class DuplicateItemsError extends AbstractValidationError
{
    public function __construct(
        int $expectedCount,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        parent::__construct(
            message: sprintf(
                'Array contains duplicate items. Expected %d unique items, but found %d at %s',
                $expectedCount,
                $actualCount,
                $dataPath,
            ),
            keyword: 'uniqueItems',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['expected' => $expectedCount, 'actual' => $actualCount],
            suggestion: 'Ensure all items in the array are unique',
        );
    }
}
