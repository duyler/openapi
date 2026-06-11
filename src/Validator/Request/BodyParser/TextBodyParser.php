<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

final readonly class TextBodyParser
{
    public function parse(string $body): string
    {
        return $body;
    }
}
