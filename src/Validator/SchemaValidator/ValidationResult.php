<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;

final readonly class ValidationResult
{
    public function __construct(
        public readonly int $validCount,
        /** @var array<int, ValidationErrorInterface> */
        public readonly array $errors,
        public readonly int $failedCount = 0,
    ) {}
}
