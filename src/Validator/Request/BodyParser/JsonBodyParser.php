<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\EmptyBodyException;

use const JSON_THROW_ON_ERROR;

final readonly class JsonBodyParser
{
    private const int JSON_MAX_DEPTH = 512;

    public function parse(string $body): array|int|string|float|bool|null
    {
        if ('' === trim($body)) {
            throw new EmptyBodyException('Request body cannot be empty');
        }

        /** @var array|int|string|float|bool|null $decoded */
        $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
