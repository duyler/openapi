<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\EmptyBodyException;
use JsonException;

use const JSON_THROW_ON_ERROR;

readonly class JsonBodyParser
{
    /**
     * @throws JsonException
     * @throws EmptyBodyException
     */
    public function parse(string $body): array|int|string|float|bool|null
    {
        if ('' === trim($body)) {
            throw new EmptyBodyException('Request body cannot be empty');
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        /** @var array|int|string|float|bool|null */
        return $decoded;
    }
}
