<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Closure;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;

/**
 * Outcome of the content assertion pipeline for a single string instance.
 *
 * The result is reported separately from the enclosing document validation:
 * in assertions mode pipeline failures reject the main validation through
 * their own exceptions, while in annotations mode this value object computes
 * its state lazily on the first accessor call and never affects the outcome.
 *
 * @api Public API: exposed through ValidationContext::contentResult().
 */
final class ContentValidationResult
{
    /**
     * @param Closure(): ContentValidationResult|null $compute
     * @param array<int, ValidationErrorInterface>|null $schemaErrors
     */
    private function __construct(
        private readonly ?Closure $compute,
        private readonly mixed $decodedValue = null,
        private readonly ?bool $mediaTypeValid = null,
        private readonly ?array $schemaErrors = null,
        private ContentValidationResult|false $resolved = false,
    ) {}

    /**
     * @param array<int, ValidationErrorInterface> $schemaErrors
     */
    public static function eager(mixed $decodedValue, bool $isMediaTypeValid, array $schemaErrors): self
    {
        return new self(
            compute: null,
            decodedValue: $decodedValue,
            mediaTypeValid: $isMediaTypeValid,
            schemaErrors: $schemaErrors,
        );
    }

    /**
     * @param Closure(): ContentValidationResult $compute
     */
    public static function lazy(Closure $compute): self
    {
        return new self(compute: $compute);
    }

    public function decodedValue(): mixed
    {
        return $this->resolve()->decodedValue;
    }

    public function isMediaTypeValid(): bool
    {
        return true === $this->resolve()->mediaTypeValid;
    }

    /**
     * @return array<int, ValidationErrorInterface>
     */
    public function schemaErrors(): array
    {
        return $this->resolve()->schemaErrors ?? [];
    }

    public function isValid(): bool
    {
        $resolved = $this->resolve();

        return true === $resolved->mediaTypeValid && [] === ($resolved->schemaErrors ?? []);
    }

    private function resolve(): self
    {
        if (null === $this->compute) {
            return $this;
        }

        if (false === $this->resolved) {
            $this->resolved = ($this->compute)();
        }

        return $this->resolved->resolve();
    }
}
