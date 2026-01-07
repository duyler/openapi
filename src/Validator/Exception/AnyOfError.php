<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

class AnyOfError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Data does not match any of the schemas at %s', $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'anyOf',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
            suggestion: 'Ensure data matches at least one of the schemas',
        );
    }
}
