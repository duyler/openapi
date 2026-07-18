<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

/**
 * Fallback validation error used when a nested validator throws a
 * `ValidationException` without structured `errors`. The original
 * message is preserved verbatim so the middleware can render a
 * diagnostic without asserting a specific nested keyword.
 */
final class NestedValidationError extends AbstractValidationError
{
    public function __construct(
        string $dataPath,
        string $schemaPath,
        string $message,
    ) {
        parent::__construct(
            message: $message,
            keyword: 'schema',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [],
        );
    }
}
