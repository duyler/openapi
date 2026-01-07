<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

interface ValidationErrorInterface
{
    public function keyword(): string;

    public function dataPath(): string;

    public function schemaPath(): string;

    public function message(): string;

    /**
     * @return array<string, mixed>
     */
    public function params(): array;

    public function suggestion(): ?string;

    /**
     * Get the exception type name (class basename)
     */
    public function getType(): string;
}
