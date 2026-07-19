<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Validator\Exception\InvalidUtf8Exception;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Override;

use const JSON_THROW_ON_ERROR;

final class JsonParser extends OpenApiBuilder
{
    #[Override]
    protected function parseContent(string $content): mixed
    {
        if (false === mb_check_encoding($content, 'UTF-8')) {
            throw new InvalidUtf8Exception('JSON spec contains invalid UTF-8 sequences');
        }

        return json_decode($content, true, JsonDepthLimit::Trusted->value, JSON_THROW_ON_ERROR);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'JSON';
    }
}
