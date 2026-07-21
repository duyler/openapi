<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Format\String;

use Override;

final readonly class BinaryValidator extends AbstractStringFormatValidator
{
    #[Override]
    protected function getFormatName(): string
    {
        return 'binary';
    }

    #[Override]
    protected function validateString(string $data): void {}
}
