<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

/** Thrown when an object contains a property not evaluated by any adjacent in-place applicator (unevaluatedProperties). */
final class UnevaluatedPropertyError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
        string $propertyName,
    ) {
        parent::__construct(
            message: sprintf('Property "%s" is not allowed and was not evaluated by any schema keyword.', $propertyName),
            keyword: 'unevaluatedProperties',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['propertyName' => $propertyName],
            suggestion: 'Remove the unevaluated property or adjust the schema to evaluate it',
        );
    }
}
