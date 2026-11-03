<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Internal;

use function filter_var;
use function sprintf;

use const FILTER_VALIDATE_URL;

/** @internal */
final class MetadataUri
{
    public static function isValid(?string $uri): bool
    {
        return null === $uri || false !== filter_var($uri, FILTER_VALIDATE_URL);
    }

    public static function invalidMessage(string $fieldName): string
    {
        return sprintf('Security scheme %s must be a valid absolute URI', $fieldName);
    }
}
