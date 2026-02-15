<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class RequiredError extends AbstractValidationError
{
    public function __construct(
        string $property,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Required property "%s" is missing at %s', $property, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'required',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['property' => $property],
            suggestion: sprintf('Add the missing property "%s" to the data', $property),
        );
    }
}
