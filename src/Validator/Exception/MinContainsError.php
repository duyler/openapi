<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

class MinContainsError extends AbstractValidationError
{
    public function __construct(
        int $minContains,
        int $actualCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Array has %d matching items, but minimum contains is %d at %s', $actualCount, $minContains, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'minContains',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['minContains' => $minContains, 'actual' => $actualCount],
            suggestion: sprintf('Ensure array has at least %d matching items', $minContains),
        );
    }
}
