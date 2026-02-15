<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class OneOfError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Data matches multiple schemas, but should match exactly one at %s', $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'oneOf',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
            suggestion: 'Ensure data matches exactly one of the schemas',
        );
    }
}
