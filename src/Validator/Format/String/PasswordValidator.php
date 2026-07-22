<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Override;

final readonly class PasswordValidator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'password';
    }

    #[Override]
    protected function validateString(string $data): void {}
}
