<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format;

interface FormatValidatorInterface
{
    public function validate(mixed $data): void;
}
