<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use function strtolower;

/** @internal */
final readonly class ByteRangePartParser
{
    private const string CONTENT_RANGE_HEADER = 'content-range';

    public static function parse(string $headerName, string $headerValue): ?ByteRange
    {
        if (self::CONTENT_RANGE_HEADER !== strtolower($headerName)) {
            return null;
        }

        return ByteRange::fromHeader($headerValue);
    }
}
