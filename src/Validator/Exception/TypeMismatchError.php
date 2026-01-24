<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

class TypeMismatchError extends AbstractValidationError
{
    public function __construct(
        string $expected,
        string $actual,
        string $dataPath,
        string $schemaPath,
        ?string $suggestion = null,
    ) {
        $message = sprintf(
            'Expected type "%s", but got "%s" at %s',
            $expected,
            $actual,
            $dataPath,
        );

        parent::__construct(
            message: $message,
            keyword: 'type',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['expected' => $expected, 'actual' => $actual],
            suggestion: $suggestion ?? sprintf('Convert the value to %s or update schema type to %s', $expected, $actual),
        );
    }
}
