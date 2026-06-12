<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

final readonly class ContentTypeNegotiator
{
    public function getMediaType(string $contentType): string
    {
        $parts = explode(';', $contentType);

        return trim($parts[0]);
    }
}
