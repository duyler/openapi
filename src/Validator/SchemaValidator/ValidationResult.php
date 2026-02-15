<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;

readonly class ValidationResult
{
    public function __construct(
        public readonly int $validCount,
        /** @var array<int, ValidationException> */
        public readonly array $errors,
        /** @var array<int, AbstractValidationError> */
        public readonly array $abstractErrors,
    ) {}
}
