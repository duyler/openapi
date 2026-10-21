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

    public function value(bool $reveal = false): mixed
    {
        return $reveal ? $this->value : '<redacted>';
    }
}
