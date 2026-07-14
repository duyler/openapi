<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

/**
 * Generic validation error used as a fallback when a nested validator throws
 * a `ValidationException` without structured `errors`. Carries the original
 * exception message verbatim so the middleware can render a meaningful
 * diagnostic without asserting a specific keyword (the actual cause may be a
 * `not` violation, `minLength`, or any other nested constraint).
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
