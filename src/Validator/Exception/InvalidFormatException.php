<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

final class InvalidFormatException extends AbstractValidationError
{
    public function __construct(
        public readonly string $format,
        protected readonly mixed $value,
        string $message,
    ) {
        parent::__construct(
            message: $message,
            keyword: 'format',
            dataPath: '',
            schemaPath: '/format',
            params: ['format' => $format],
        );
    }

    /**
     * Returns the validated value. Pass $reveal = true only from trusted
     * operator code (security auditor, verbose logger with opt-in
     * includeSensitiveValues); the default returns '<redacted>' to
     * prevent disclosure of credentials (passwords, tokens) through
     * reflective serialization.
     */
    public function value(bool $reveal = false): mixed
    {
        return $reveal ? $this->value : '<redacted>';
    }
}
