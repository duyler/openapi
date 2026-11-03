<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function str_starts_with;
use function strtolower;

final readonly class StreamingMultipartDetector
{
    public static function isStreaming(string $contentType): bool
    {
        return str_starts_with(strtolower($contentType), 'multipart/');
    }
}
