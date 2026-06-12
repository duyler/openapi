<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Exception;
use Throwable;

final class ValidationException extends Exception
{
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
