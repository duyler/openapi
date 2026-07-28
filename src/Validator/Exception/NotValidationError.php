<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

/** Thrown when data matches the schema forbidden by the not keyword. */
final class NotValidationError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
    ) {
        parent::__construct(
            message: 'Data must NOT match the "not" schema',
            keyword: 'not',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
            suggestion: 'Adjust the data so it does not match the schema forbidden by "not", or relax the "not" schema',
        );
    }
}
