<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;

/**
 * Interface for formatting validation errors for display
 */
interface ErrorFormatterInterface
{
    /**
     * Format validation error for display
     *
     * @param AbstractValidationError $error The error to format
     * @return string Formatted error message
     */
    public function format(AbstractValidationError $error): string;

    /**
     * Format multiple errors
     *
     * @param array<int, AbstractValidationError> $errors
     * @return string Formatted error messages
     */
    public function formatMultiple(array $errors): string;
}
