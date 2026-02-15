<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

readonly class FormBodyParser
{
    /**
     * @return array<array-key, mixed>
     */
    public function parse(string $body): array
    {
        if ('' === trim($body)) {
            return [];
        }

        $params = [];
        parse_str($body, $params);

        return $params;
    }
}
