<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

final readonly class ContentTypeNegotiator
{
    public function getMediaType(string $contentType): string
    {
        // Extract mime type without parameters
        $parts = explode(';', $contentType);

        return trim($parts[0]);
    }

    public function getCharset(string $contentType): ?string
    {
        // Extract charset parameter
        preg_match('/charset=([^;]+)/', $contentType, $matches);

        return $matches[1] ?? null;
    }
}
