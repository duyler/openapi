<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

use Override;

final readonly class Base64UrlSyntaxCheck extends AbstractBaseEncodingSyntaxCheck
{
    private const string ALPHABET = '[A-Za-z0-9_-]';

    private const int QUANTUM_SIZE = 4;

    private const array PADDING_BY_REMAINDER = [2 => 2, 3 => 1];

    #[Override]
    protected function alphabet(): string
    {
        return self::ALPHABET;
    }

    #[Override]
    protected function quantumSize(): int
    {
        return self::QUANTUM_SIZE;
    }

    #[Override]
    protected function paddingByRemainder(): array
    {
        return self::PADDING_BY_REMAINDER;
    }
}
