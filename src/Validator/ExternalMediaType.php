<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use function is_array;

final readonly class ExternalMediaType
{
    /**
     * @param string|list<string>|null $contentTypeFromEncoding
     */
    public static function isDefined(string|array|null $contentTypeFromEncoding, ?string $contentMapKey): bool
    {
        if (null === $contentTypeFromEncoding) {
            return null !== $contentMapKey;
        }

        return [] !== $contentTypeFromEncoding || null !== $contentMapKey;
    }

    /**
     * @param string|list<string>|null $contentTypeFromEncoding
     */
    public static function primary(string|array|null $contentTypeFromEncoding, string $fallback): string
    {
        if (is_array($contentTypeFromEncoding)) {
            return $contentTypeFromEncoding[0] ?? $fallback;
        }

        return $contentTypeFromEncoding ?? $fallback;
    }
}
