<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;

/**
 * Interface for formatting validation errors for display
 */
interface ErrorFormatterInterface
{
    /**
     * Format validation error for display
     *
     * @param ValidationErrorInterface $error The error to format
     * @return string Formatted error message
     */
    public function format(ValidationErrorInterface $error): string;

    /**
     * Format multiple errors
     *
     * @param array<int, ValidationErrorInterface> $errors
     * @return string Formatted error messages
     */
    public function formatMultiple(array $errors): string;

    /**
     * Format a ValidationException's errors into a single string.
     *
     * Replaces OpenApiValidatorInterface::getFormattedErrors() as the
     * canonical way to format validation errors without holding a
     * reference to the validator.
     *
     * @param ValidationException $exception Validation exception whose errors to format
     * @return string Formatted error messages
     */
    public function formatException(ValidationException $exception): string;
}
