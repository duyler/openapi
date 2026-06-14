<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use function strtolower;
use function trim;

final readonly class ContentTypeNegotiator
{
    public function getMediaType(string $contentType): string
    {
        $parts = explode(';', $contentType);

        return strtolower(trim($parts[0]));
    }
}
