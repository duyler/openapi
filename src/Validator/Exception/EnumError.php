<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function is_scalar;
use function sprintf;

final class EnumError extends AbstractValidationError
{
    public function __construct(
        array $allowedValues,
        mixed $actual,
        string $dataPath,
        string $schemaPath,
    ) {
        $actualJson = json_encode($actual);

        if ($actualJson === false) {
            $actualJson = 'null';
        }

        $allowedValuesJson = json_encode($allowedValues);

        if ($allowedValuesJson === false) {
            $allowedValuesJson = 'null';
        }

        $allowedValuesStrings = array_map(
            fn($value) => is_scalar($value) ? (string) $value : json_encode($value),
            $allowedValues,
        );

        $message = sprintf('Value "%s" is not in allowed values: %s at %s', $actualJson, $allowedValuesJson, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'enum',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['allowed' => $allowedValues, 'actual' => $actual],
            suggestion: sprintf('Use one of the allowed values: %s', implode(', ', $allowedValuesStrings)),
        );
    }
}
