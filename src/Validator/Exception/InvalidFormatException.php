<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Override;
use RuntimeException;

class InvalidFormatException extends RuntimeException implements ValidationErrorInterface
{
    public function __construct(
        public readonly string $format,
        public readonly mixed $value,
        string $message,
    ) {
        parent::__construct($message);
    }

    #[Override]
    public function keyword(): string
    {
        return 'format';
    }

    #[Override]
    public function dataPath(): string
    {
        return '';
    }

    #[Override]
    public function schemaPath(): string
    {
        return '/format';
    }

    #[Override]
    public function message(): string
    {
        return $this->getMessage();
    }

    #[Override]
    public function params(): array
    {
        return [
            'format' => $this->format,
            'value' => $this->value,
        ];
    }

    #[Override]
    public function suggestion(): ?string
    {
        return null;
    }

    #[Override]
    public function getType(): string
    {
        return 'format';
    }
}
