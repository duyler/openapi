<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

final class NestedValidationError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
        string $message,
    ) {
        parent::__construct(
            message: $message,
            keyword: 'schema',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
        );
    }
}
