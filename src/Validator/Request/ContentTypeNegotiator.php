<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

readonly class ContentTypeNegotiator
{
    public function getMediaType(string $contentType): string
    {
        $parts = explode(';', $contentType);

        return trim($parts[0]);
    }

    public function getCharset(string $contentType): ?string
    {
        preg_match('/charset=([^;]+)/', $contentType, $matches);

        return $matches[1] ?? null;
    }
}
