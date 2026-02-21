<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Override;
use Symfony\Component\Yaml\Yaml;

final class YamlParser extends OpenApiBuilder
{
    #[Override]
    protected function parseContent(string $content): mixed
    {
        return Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'YAML';
    }
}
