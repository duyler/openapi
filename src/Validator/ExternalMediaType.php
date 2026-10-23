<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

final readonly class ExternalMediaType
{
    public static function isDefined(?string $contentTypeFromEncoding, ?string $contentMapKey): bool
    {
        return null !== $contentTypeFromEncoding || null !== $contentMapKey;
    }
}
