<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

use Override;

final readonly class Base32HexSyntaxCheck extends AbstractBaseEncodingSyntaxCheck
{
    private const string ALPHABET = '[0-9A-V]';

    private const int QUANTUM_SIZE = 8;

    private const array PADDING_BY_REMAINDER = [2 => 6, 4 => 4, 5 => 3, 7 => 1];

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
