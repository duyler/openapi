<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Exception;
use Throwable;

/**
 * Thrown when request, response, or schema validation fails.
 *
 * Subclasses MUST preserve the contract of getErrors() returning
 * array<int, ValidationErrorInterface> so consumers can iterate the
 * typed error list uniformly.
 *
 * @api Public API: consumers may subclass to attach domain context.
 */
class ValidationException extends Exception
{
    use SanitizableExceptionTrait;

    /**
     * @param array<int, ValidationErrorInterface> $errors
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly array $errors = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<int, ValidationErrorInterface>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
