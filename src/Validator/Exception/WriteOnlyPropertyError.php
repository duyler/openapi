<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class WriteOnlyPropertyError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
        string $propertyName,
    ) {
        parent::__construct(
            message: sprintf('Property "%s" is write-only and must not be received in a response', $propertyName),
            keyword: 'writeOnly',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['propertyName' => $propertyName],
            suggestion: 'Remove the write-only property from the response payload',
        );
    }
}
