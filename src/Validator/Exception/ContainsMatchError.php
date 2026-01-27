<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

final class ContainsMatchError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
    ) {
        parent::__construct(
            message: 'Array does not contain any item matching the schema.',
            keyword: 'contains',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
            suggestion: 'Ensure at least one item in the array matches the specified schema',
        );
    }
}
