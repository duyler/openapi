<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;

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
}
