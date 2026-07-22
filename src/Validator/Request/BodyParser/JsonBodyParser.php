<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\JsonDepthLimit;

use function strlen;

use const JSON_THROW_ON_ERROR;

final readonly class JsonBodyParser
{
    private const int JSON_MAX_DEPTH = JsonDepthLimit::Untrusted->value;

    private const string UTF8_BOM = "\xEF\xBB\xBF";

    public function parse(string $body): array|int|string|float|bool|null
    {
        if (str_starts_with($body, self::UTF8_BOM)) {
            $body = substr($body, strlen(self::UTF8_BOM));
        }

        if (false === mb_check_encoding($body, 'UTF-8')) {
            throw new InvalidUtf8Exception('JSON body contains invalid UTF-8 sequences');
        }

        if ('' === trim($body)) {
            return null;
        }

        /** @var array|int|string|float|bool|null $decoded */
        $decoded = json_decode($body, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
