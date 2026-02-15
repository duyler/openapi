<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class MultipleOfKeywordError extends AbstractValidationError
{
    public function __construct(
        float $multipleOf,
        float|int $value,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf('Value %f is not a multiple of %f at %s', $value, $multipleOf, $dataPath);

        parent::__construct(
            message: $message,
            keyword: 'multipleOf',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['multipleOf' => $multipleOf, 'value' => $value],
            suggestion: sprintf('Value must be a multiple of %f', $multipleOf),
        );
    }
}
