<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use InvalidArgumentException;
use Override;
use Throwable;

final class InvalidDataTypeException extends InvalidArgumentException implements ValidationErrorInterface
{
    public readonly string $type;

    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->type = 'invalid';
    }

    #[Override]
    public function keyword(): string
    {
        return 'invalid';
    }

    #[Override]
    public function dataPath(): string
    {
        return '';
    }

    #[Override]
    public function schemaPath(): string
    {
        return '';
    }

    #[Override]
    public function message(): string
    {
        return $this->getMessage();
    }

    #[Override]
    public function params(): array
    {
        return [];
    }

    #[Override]
    public function suggestion(): ?string
    {
        return null;
    }

    #[Override]
    public function getType(): string
    {
        return $this->type;
    }
}
