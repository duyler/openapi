<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

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
