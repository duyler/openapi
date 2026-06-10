<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Override;

use const JSON_THROW_ON_ERROR;

final class JsonParser extends OpenApiBuilder
{
    private const int JSON_MAX_DEPTH = 512;

    #[Override]
    protected function parseContent(string $content): mixed
    {
        return json_decode($content, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'JSON';
    }
}
