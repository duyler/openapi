<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

/** Thrown when a readOnly property is sent in a request payload. */
final class ReadOnlyPropertyError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
        string $propertyName,
    ) {
        parent::__construct(
            message: sprintf('Property "%s" is read-only and must not be sent in a request', $propertyName),
            keyword: 'readOnly',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['propertyName' => $propertyName],
            suggestion: 'Remove the read-only property from the request payload',
        );
    }
}
