<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Override;

use const JSON_THROW_ON_ERROR;

readonly class JsonParser extends OpenApiBuilder
{
    #[Override]
    protected function parseContent(string $content): mixed
    {
        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'JSON';
    }
}
