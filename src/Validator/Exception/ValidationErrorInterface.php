<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Deprecated;

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

    #[Deprecated(message: 'Use keyword() instead. This method will be removed in 2.0.')]
    public function getType(): string;
}
