<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

class MaxContainsError extends AbstractValidationError
{
    public function __construct(
        int $maxContains,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Array has %d matching items, but maximum contains is %d at %s', $actualCount, $maxContains, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'maxContains',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['maxContains' => $maxContains, 'actual' => $actualCount],
            suggestion: sprintf('Ensure array has at most %d matching items', $maxContains),
        );
    }
}
