<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

use Override;

final readonly class Base16SyntaxCheck extends AbstractBaseEncodingSyntaxCheck
{
    private const string ALPHABET = '[0-9A-Fa-f]';

    private const int QUANTUM_SIZE = 2;

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
        return [];
    }
}
