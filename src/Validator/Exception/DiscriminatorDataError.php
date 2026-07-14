<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

/**
 * Validation error raised when discriminator-driven oneOf validation
 * receives non-object data (for example a scalar or null without a
 * nullable subschema). The discriminator maps a property value to a
 * concrete subschema, which requires an object as input.
 */
final class DiscriminatorDataError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
    ) {
        parent::__construct(
            message: 'Discriminator validation failed: data must be an object',
            keyword: 'oneOf',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
            suggestion: 'Provide an object with the discriminator property declared in the schema',
        );
    }
}
