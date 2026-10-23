<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Util\Encoding;

interface EncodingSyntaxCheck
{
    public function isValid(string $data): bool;
}
