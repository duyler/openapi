<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class DependentRequiredError extends AbstractValidationError
{
    public function __construct(
        string $property,
        string $triggerProperty,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf(
            'Property "%s" is required by dependent property "%s" but is missing at %s',
            $property,
            $triggerProperty,
            $dataPath,
        );

        parent::__construct(
            message: $message,
            keyword: 'dependentRequired',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['property' => $property, 'triggerProperty' => $triggerProperty],
            suggestion: sprintf('Add the missing property "%s" required by "%s"', $property, $triggerProperty),
        );
    }
}
