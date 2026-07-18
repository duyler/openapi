<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;

interface ErrorFormatterInterface
{
    public function format(ValidationErrorInterface $error): string;

    /**
     * @param array<int, ValidationErrorInterface> $errors
     */
    public function formatMultiple(array $errors): string;

    /**
     * Format a ValidationException's errors into a single string.
     *
     * Replaces OpenApiValidatorInterface::getFormattedErrors() as the
     * canonical way to format validation errors without holding a
     * reference to the validator.
     */
    public function formatException(ValidationException $exception): string;
}
